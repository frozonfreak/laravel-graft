<?php

namespace GraftAI\Http\Controllers\Api;

use GraftAI\Dsl\PipelineSignature;
use GraftAI\Dsl\PolicyEngine;
use GraftAI\Models\AuditEvent;
use GraftAI\Models\FeatureConfig;
use GraftAI\Models\FeatureSnapshot;
use GraftAI\Models\Tenant;
use GraftAI\Services\AiSpecGenerator;
use GraftAI\Services\SemanticValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Feature lifecycle: generate → confirm → save → execute → rollback.
 *
 * The AI → Confirm → Save flow (spec §9):
 *  POST /api/features/generate  — AI generates DSL config from NL prompt
 *  POST /api/features           — User confirms + saves (after reading semantic summary)
 *  GET  /api/features           — List tenant features
 *  GET  /api/features/{id}      — Get single feature
 *  DELETE /api/features/{id}    — Archive feature
 *  POST /api/features/{id}/rollback — Rollback to prior version
 */
class FeatureController extends Controller
{
    public function __construct(
        private readonly PolicyEngine    $policy,
        private readonly AiSpecGenerator $aiGenerator,
        private readonly SemanticValidator $semanticValidator,
    ) {}

    /**
     * Generate a DSL config from a natural language prompt.
     * Returns the config + semantic summary for user confirmation.
     * Config is NOT saved at this stage.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate(['prompt' => 'required|string|max:1000']);

        $tenant = $this->resolveTenant($request);
        $result = $this->aiGenerator->generate($request->input('prompt'), $tenant);

        if ($result['error']) {
            return response()->json(['error' => $result['error']], 422);
        }

        $config  = $result['config'];
        $summary = $this->semanticValidator->summarize($config);

        // Run Stage 1 to surface any policy errors before confirmation
        $stage1 = $this->policy->stage1($config, $tenant);

        return response()->json([
            'config'          => $config,
            'semantic_summary' => $summary,
            'policy'          => [
                'ok'           => $stage1['ok'],
                'errors'       => $stage1['errors'],
                'trust_tier'   => $stage1['trust_tier'],
                'cost_estimate' => $stage1['cost_estimate'],
            ],
        ]);
    }

    /**
     * Save a confirmed feature config.
     * Requires the user to have read and confirmed the semantic summary.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'config'   => 'required|array',
            'confirmed' => 'required|boolean|accepted',
        ]);

        $tenant = $this->resolveTenant($request);
        $config = $request->input('config');

        $stage1 = $this->policy->stage1($config, $tenant);

        if (! $stage1['ok']) {
            return response()->json(['errors' => $stage1['errors']], 422);
        }

        $trustTier   = $stage1['trust_tier'];
        $costEstimate = $stage1['cost_estimate'];

        // Cost acknowledgment required for 'high' tier
        if ($costEstimate['tier'] === 'high' && ! $request->boolean('cost_acknowledged')) {
            return response()->json([
                'error'        => 'Cost acknowledgment required.',
                'cost_estimate' => $costEstimate,
            ], 422);
        }

        $signature = PipelineSignature::compute($config['data_source'], $config['pipeline']);

        $status = $trustTier >= 3 ? 'pending_approval' : 'active';

        $feature = FeatureConfig::create([
            'tenant_id'              => $tenant->id,
            'dsl_version'            => $config['dsl_version'] ?? '1.0',
            'feature_version'        => 1,
            'lifecycle_stage'        => 'sandbox',
            'type'                   => $config['type'],
            'data_source'            => $config['data_source'],
            'pipeline'               => $config['pipeline'],
            'action'                 => $config['action'],
            'schedule'               => $config['schedule'] ?? null,
            'status'                 => $status,
            'trust_tier'             => $trustTier,
            'cost_estimate'          => $costEstimate,
            'pipeline_signature'     => $signature,
            'contributes_to_evolution' => $tenant->contributesToEvolution(),
            'created_by'             => 'ai',
        ]);

        AuditEvent::log($tenant->id, $feature->id, 'feature_created', 'user', [
            'trust_tier' => $trustTier,
            'status'     => $status,
        ]);

        return response()->json($feature, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $tenant   = $this->resolveTenant($request);
        $features = FeatureConfig::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($features);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenant  = $this->resolveTenant($request);
        $feature = FeatureConfig::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        return response()->json($feature);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenant  = $this->resolveTenant($request);
        $feature = FeatureConfig::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $feature->update(['status' => 'archived']);

        AuditEvent::log($tenant->id, $feature->id, 'feature_archived', 'user');

        return response()->json(['message' => 'Feature archived.']);
    }

    /**
     * Rollback a feature to a prior snapshot version.
     */
    public function rollback(Request $request, string $id): JsonResponse
    {
        $request->validate(['snapshot_id' => 'required|uuid']);

        $tenant   = $this->resolveTenant($request);
        $feature  = FeatureConfig::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $snapshot = FeatureSnapshot::where('id', $request->input('snapshot_id'))
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $snapshotData = collect($snapshot->features)
            ->firstWhere('id', $feature->id);

        if (! $snapshotData) {
            return response()->json(['error' => 'Feature not found in snapshot.'], 404);
        }

        $allowedFields = [
            'pipeline', 'action', 'schedule', 'status',
            'dsl_version', 'data_source', 'type',
        ];

        $feature->update(array_intersect_key($snapshotData, array_flip($allowedFields)));
        $feature->increment('feature_version');

        AuditEvent::log($tenant->id, $feature->id, 'feature_rolled_back', 'user', [
            'snapshot_id' => $snapshot->id,
        ]);

        return response()->json($feature->fresh());
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');

        return Tenant::findOrFail($tenantId);
    }
}

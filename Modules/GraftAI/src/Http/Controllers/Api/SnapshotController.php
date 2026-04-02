<?php

namespace GraftAI\Http\Controllers\Api;

use GraftAI\Dsl\DslDefinition;
use GraftAI\Models\CapabilityRegistry;
use GraftAI\Models\FeatureSnapshot;
use GraftAI\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Snapshot management for tenant-level rollback.
 *
 * POST /api/snapshots           — Create a tenant snapshot
 * GET  /api/snapshots           — List tenant snapshots
 * GET  /api/snapshots/{id}      — Get snapshot (tenant-scoped — 404 if wrong tenant)
 */
class SnapshotController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['label' => 'nullable|string|max:255']);

        $tenant = $this->resolveTenant($request);
        $dslVersion = CapabilityRegistry::orderByDesc('introduced_in_dsl')
            ->value('introduced_in_dsl') ?? DslDefinition::CURRENT_VERSION;

        $snapshot = FeatureSnapshot::forTenant(
            $tenant->id,
            $request->input('label', ''),
            $dslVersion,
            'user',
        );

        return response()->json($snapshot, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $snapshots = FeatureSnapshot::where('tenant_id', $tenant->id)
            ->where('snapshot_type', 'tenant')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($snapshots);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        $snapshot = FeatureSnapshot::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        return response()->json($snapshot);
    }

    private function resolveTenant(Request $request): Tenant
    {
        return Tenant::findOrFail($request->header('X-Tenant-ID'));
    }
}

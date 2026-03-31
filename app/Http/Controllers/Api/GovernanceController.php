<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CapabilityRegistry;
use App\Models\EvolutionEvent;
use App\Models\PromotionCandidate;
use App\Services\PromotionPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Governance dashboard endpoints for reviewing and promoting candidates.
 *
 * GET  /api/governance/candidates         — List pending candidates
 * POST /api/governance/candidates/{id}/approve  — Approve a candidate
 * POST /api/governance/candidates/{id}/reject   — Reject a candidate
 * POST /api/governance/candidates/{id}/promote  — Promote an approved candidate
 * POST /api/governance/capabilities/{id}/rollback — Roll back a capability
 * GET  /api/governance/evolution-log      — View the evolution log
 */
class GovernanceController extends Controller
{
    public function __construct(
        private readonly PromotionPipeline $pipeline,
    ) {}

    public function candidates(Request $request): JsonResponse
    {
        $status     = $request->input('status', 'pending');
        $candidates = PromotionCandidate::where('status', $status)
            ->orderByDesc('weighted_exec_score')
            ->get();

        return response()->json($candidates);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $candidate = PromotionCandidate::findOrFail($id);

        if (! $candidate->isPending()) {
            return response()->json(['error' => 'Candidate is not in pending state.'], 422);
        }

        $reviewer = $request->input('reviewer', 'dev_review');
        $candidate->approve($reviewer);

        return response()->json($candidate->fresh());
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $candidate = PromotionCandidate::findOrFail($id);
        $reviewer  = $request->input('reviewer', 'dev_review');
        $candidate->reject($reviewer);

        return response()->json($candidate->fresh());
    }

    /**
     * Revert an approved or rejected candidate back to pending.
     * Does not touch the Capability Registry — only the candidate record.
     */
    public function revertToPending(Request $request, string $id): JsonResponse
    {
        $candidate = PromotionCandidate::findOrFail($id);

        if (! in_array($candidate->status, ['approved', 'rejected'], true)) {
            return response()->json(['error' => 'Only approved or rejected candidates can be reverted to pending.'], 422);
        }

        $candidate->update([
            'status'      => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return response()->json($candidate->fresh());
    }

    /**
     * Roll back a promoted candidate: deprecates the associated capability
     * and marks the candidate as promoted_then_reverted.
     */
    public function rollbackCandidate(Request $request, string $id): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        $candidate = PromotionCandidate::findOrFail($id);

        if ($candidate->status !== 'promoted') {
            return response()->json(['error' => 'Only promoted candidates can be rolled back.'], 422);
        }

        // Find the capability that was created by this candidate's promotion
        $capability = CapabilityRegistry::where('introduced_by', 'promotion:' . $candidate->id)->first();

        if (! $capability) {
            return response()->json(['error' => 'No capability found for this candidate. It may have been rolled back already.'], 404);
        }

        $this->pipeline->rollback(
            $capability,
            $request->input('rolled_back_by', 'dev_review'),
            $request->input('notes', ''),
        );

        return response()->json([
            'message'   => "Candidate rolled back. Capability '{$capability->name}' deprecated.",
            'candidate' => $candidate->fresh(),
        ]);
    }

    public function promote(Request $request, string $id): JsonResponse
    {
        $request->validate(['operator_name' => 'required|string|alpha_dash|max:100']);

        $candidate    = PromotionCandidate::findOrFail($id);
        $operatorName = $request->input('operator_name');
        $promotedBy   = $request->input('promoted_by', 'dev_review');

        $this->pipeline->promote($candidate, $operatorName, $promotedBy);

        return response()->json([
            'message'  => "Operator '{$operatorName}' promoted successfully.",
            'candidate' => $candidate->fresh(),
        ]);
    }

    public function rollbackCapability(Request $request, string $id): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        $capability  = CapabilityRegistry::findOrFail($id);
        $rollbackBy  = $request->input('rolled_back_by', 'dev_review');
        $notes       = $request->input('notes', '');

        $this->pipeline->rollback($capability, $rollbackBy, $notes);

        return response()->json([
            'message'    => "Capability '{$capability->name}' rolled back.",
            'capability' => $capability->fresh(),
        ]);
    }

    public function evolutionLog(Request $request): JsonResponse
    {
        $events = EvolutionEvent::orderByDesc('promoted_at')
            ->paginate(50);

        return response()->json($events);
    }

    public function capabilities(Request $request): JsonResponse
    {
        $capabilities = CapabilityRegistry::orderBy('introduced_in_dsl')->get();

        return response()->json($capabilities);
    }
}

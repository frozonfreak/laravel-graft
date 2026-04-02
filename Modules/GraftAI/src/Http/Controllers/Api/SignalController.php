<?php

namespace GraftAI\Http\Controllers\Api;

use GraftAI\Models\ExecutionSignal;
use GraftAI\Models\FeatureConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * User feedback on executions (useful / not_useful).
 *
 * POST /api/signals/{id}/feedback  — Submit feedback for an execution signal
 */
class SignalController extends Controller
{
    public function feedback(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'feedback' => 'required|in:useful,not_useful',
        ]);

        $signal = ExecutionSignal::findOrFail($id);

        $tenantId = $request->header('X-Tenant-ID');
        $feature  = FeatureConfig::where('id', $signal->feature_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $signal->update(['user_feedback' => $request->input('feedback')]);

        return response()->json(['message' => 'Feedback recorded.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExecutionSignal;
use App\Models\FeatureConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Verify the feature belongs to the requesting tenant
        $tenantId = $request->header('X-Tenant-ID');
        $feature  = FeatureConfig::where('id', $signal->feature_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $signal->update(['user_feedback' => $request->input('feedback')]);

        return response()->json(['message' => 'Feedback recorded.']);
    }
}

<?php

namespace GraftAI\Services;

use GraftAI\Models\ExecutionSignal;
use GraftAI\Models\FeatureConfig;
use GraftAI\Models\FeatureExecution;

/**
 * Emits anonymized execution signals to the evolution pipeline.
 * tenant_id is deliberately never included.
 */
class SignalEmitter
{
    public function emit(FeatureConfig $feature, FeatureExecution $execution): void
    {
        if (! $feature->contributes_to_evolution) {
            return;
        }

        if (! $feature->tenant->contributesToEvolution()) {
            return;
        }

        ExecutionSignal::create([
            'feature_id' => $feature->id,
            'pipeline_signature' => $feature->pipeline_signature,
            // tenant_id deliberately omitted — anonymized
            'dsl_version' => $feature->dsl_version,
            'data_source' => $feature->data_source,
            'execution_outcome' => $execution->status,
            'action_triggered' => null,
            'execution_ms' => $execution->execution_ms,
            'rows_scanned' => $execution->rows_scanned,
            'emitted_at' => now(),
        ]);

        $execution->update(['signal_emitted' => true]);
    }

    public function emitWithOutcome(
        FeatureConfig $feature,
        FeatureExecution $execution,
        bool $actionTriggered,
    ): void {
        if (! $feature->contributes_to_evolution) {
            return;
        }

        if (! $feature->tenant->contributesToEvolution()) {
            return;
        }

        ExecutionSignal::create([
            'feature_id' => $feature->id,
            'pipeline_signature' => $feature->pipeline_signature,
            'dsl_version' => $feature->dsl_version,
            'data_source' => $feature->data_source,
            'execution_outcome' => $execution->status,
            'action_triggered' => $actionTriggered,
            'execution_ms' => $execution->execution_ms,
            'rows_scanned' => $execution->rows_scanned,
            'emitted_at' => now(),
        ]);

        $execution->update(['signal_emitted' => true]);
    }
}

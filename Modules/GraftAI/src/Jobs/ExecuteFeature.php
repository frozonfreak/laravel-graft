<?php

namespace GraftAI\Jobs;

use GraftAI\Dsl\PipelineExecutor;
use GraftAI\Dsl\PolicyEngine;
use GraftAI\Models\AuditEvent;
use GraftAI\Models\FeatureConfig;
use GraftAI\Models\FeatureExecution;
use GraftAI\Models\TenantBudget;
use GraftAI\Services\ActionDispatcher;
use GraftAI\Services\DataSourceLoader;
use GraftAI\Services\SignalEmitter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued job that executes a single FeatureConfig pipeline.
 *
 * Idempotency key: {feature_id}:{execution_date}:{feature_version}
 * Soft time limit: 25s  / Hard limit: 30s (enforced by Laravel Job timeout)
 */
class ExecuteFeature implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries   = 1;

    public function __construct(
        public readonly string $featureId,
        public readonly string $tenantId,
        public readonly string $executionDate,
    ) {}

    public function handle(
        PolicyEngine    $policy,
        PipelineExecutor $executor,
        DataSourceLoader $loader,
        SignalEmitter   $emitter,
        ActionDispatcher $dispatcher,
    ): void {
        $feature = FeatureConfig::findOrFail($this->featureId);

        // Stage 2 policy check
        $check = $policy->stage2($feature, $this->tenantId);
        if (! $check['ok']) {
            AuditEvent::log($this->tenantId, $this->featureId, 'execution_blocked', 'system', [
                'reason' => $check['reason'],
            ]);
            return;
        }

        $execution = FeatureExecution::create([
            'feature_id'  => $this->featureId,
            'tenant_id'   => $this->tenantId,
            'status'      => 'running',
            'started_at'  => now(),
        ]);

        $startMs = microtime(true);

        try {
            // Load data — tenant_id comes from execution context, never from config
            $data = $loader->load($feature->data_source, $this->tenantId);

            if ($data->count() > 100000) {
                throw new \RuntimeException('Row count pre-check exceeded limit of 100,000.');
            }

            $result = $executor->execute($feature->pipeline, $data);

            $elapsedMs   = (int) round((microtime(true) - $startMs) * 1000);
            $rowsScanned = $result['rows_scanned'];

            $execution->update([
                'status'       => 'success',
                'completed_at' => now(),
                'execution_ms' => $elapsedMs,
                'rows_scanned' => $rowsScanned,
                'cost_actual'  => $feature->cost_estimate['score'] ?? 0,
            ]);

            $feature->update(['last_executed_at' => now()]);

            $budget = TenantBudget::getOrCreate($this->tenantId);
            $budget->addCost($feature->cost_estimate['score'] ?? 0);

            if ($result['action_triggered']) {
                $dispatcher->dispatch($feature->action, $result['rows'], $feature);
            }

            $emitter->emitWithOutcome($feature, $execution, $result['action_triggered']);

            AuditEvent::log($this->tenantId, $this->featureId, 'execution_success', 'system', [
                'execution_id' => $execution->id,
                'rows_scanned' => $rowsScanned,
                'execution_ms' => $elapsedMs,
            ]);

        } catch (\Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $startMs) * 1000);

            $execution->update([
                'status'       => 'failure',
                'completed_at' => now(),
                'execution_ms' => $elapsedMs,
                'error_detail' => [
                    'message' => $e->getMessage(),
                    'class'   => get_class($e),
                ],
            ]);

            AuditEvent::log($this->tenantId, $this->featureId, 'execution_failure', 'system', [
                'error' => $e->getMessage(),
            ]);

            $feature->refresh();
            if ($feature->consecutiveFailureCount() >= 3) {
                $feature->suspend();
                AuditEvent::log($this->tenantId, $this->featureId, 'feature_auto_suspended', 'system', [
                    'reason' => '3 consecutive failures',
                ]);
            }

            $emitter->emit($feature, $execution);
        }
    }

    /**
     * Idempotency key prevents duplicate executions for the same feature/date/version.
     */
    public static function idempotencyKey(string $featureId, string $date, int $version): string
    {
        return "{$featureId}:{$date}:{$version}";
    }
}

<?php

namespace GraftAI\Console\Commands;

use GraftAI\Models\PromotionCandidate;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pattern Detector — runs daily via the scheduler.
 *
 * Queries anonymized execution signals to find pipeline shapes that
 * meet promotion thresholds and enqueues them as PromotionCandidates.
 *
 * Promotion thresholds (from spec):
 *   - Distinct opted-in tenants using same pipeline shape:  ≥ 3
 *   - Weighted execution score (Σ min(feature_exec_count, 500)): ≥ 200
 *   - Distinct features with this pipeline shape:           ≥ 5
 *   - Success rate:                                         ≥ 90%
 */
class DetectPatterns extends Command
{
    protected $signature = 'evolution:detect-patterns';

    protected $description = 'Run the pattern detector to identify promotion candidates from execution signals.';

    private const MIN_DISTINCT_TENANTS = 3;

    private const MIN_WEIGHTED_SCORE = 200;

    private const MIN_DISTINCT_FEATURES = 5;

    private const MIN_SUCCESS_RATE = 0.90;

    private const EXEC_COUNT_CAP = 500;

    public function handle(): int
    {
        $this->info('Pattern detector running...');

        $candidates = $this->queryCandidates();

        $created = 0;
        $updated = 0;

        foreach ($candidates as $row) {
            $existing = PromotionCandidate::where('pipeline_signature', $row->pipeline_signature)->first();

            $riskTier = $this->assessRisk($row->pipeline_signature);

            if ($existing) {
                $existing->update([
                    'distinct_tenants' => $row->distinct_tenants,
                    'distinct_features' => $row->distinct_features,
                    'weighted_exec_score' => $row->weighted_exec_score,
                    'success_rate' => $row->avg_success_rate,
                    'risk_tier' => $riskTier,
                ]);
                $updated++;
            } else {
                $candidate = PromotionCandidate::create([
                    'pipeline_signature' => $row->pipeline_signature,
                    'distinct_tenants' => $row->distinct_tenants,
                    'distinct_features' => $row->distinct_features,
                    'weighted_exec_score' => $row->weighted_exec_score,
                    'success_rate' => $row->avg_success_rate,
                    'risk_tier' => $riskTier,
                    'status' => 'pending',
                ]);

                if ($candidate->isAutoApprovable()) {
                    $candidate->approve('system:auto');
                    $this->info("Auto-approved low-risk candidate: {$candidate->pipeline_signature}");
                }

                $created++;
            }
        }

        $this->info("Pattern detection complete. Created: {$created}, Updated: {$updated}.");

        return self::SUCCESS;
    }

    private function queryCandidates(): Collection
    {
        return DB::table('execution_signals as es')
            ->join(
                DB::raw('(
                    SELECT feature_id,
                           pipeline_signature,
                           LEAST(COUNT(*), '.self::EXEC_COUNT_CAP.') AS capped_count,
                           AVG(CASE WHEN execution_outcome = \'success\' THEN 1.0 ELSE 0.0 END) AS feature_success_rate
                    FROM execution_signals
                    GROUP BY feature_id, pipeline_signature
                ) AS feature_summary'),
                'es.feature_id',
                '=',
                'feature_summary.feature_id'
            )
            ->join('feature_configs AS fc', 'es.feature_id', '=', 'fc.id')
            ->join('tenants AS t', 'fc.tenant_id', '=', 't.id')
            ->where('fc.contributes_to_evolution', true)
            ->whereJsonContains('t.evolution_settings->contribute_to_pattern_detection', true)
            ->select(
                'es.pipeline_signature',
                DB::raw('COUNT(DISTINCT fc.tenant_id) AS distinct_tenants'),
                DB::raw('COUNT(DISTINCT es.feature_id) AS distinct_features'),
                DB::raw('SUM(feature_summary.capped_count) AS weighted_exec_score'),
                DB::raw('AVG(feature_summary.feature_success_rate) AS avg_success_rate')
            )
            ->groupBy('es.pipeline_signature')
            ->havingRaw('COUNT(DISTINCT fc.tenant_id) >= ?', [self::MIN_DISTINCT_TENANTS])
            ->havingRaw('SUM(feature_summary.capped_count) >= ?', [self::MIN_WEIGHTED_SCORE])
            ->havingRaw('COUNT(DISTINCT es.feature_id) >= ?', [self::MIN_DISTINCT_FEATURES])
            ->havingRaw('AVG(feature_summary.feature_success_rate) >= ?', [self::MIN_SUCCESS_RATE])
            ->orderByDesc('weighted_exec_score')
            ->get();
    }

    private function assessRisk(string $signature): string
    {
        return 'medium';
    }
}

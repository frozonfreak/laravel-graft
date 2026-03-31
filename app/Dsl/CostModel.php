<?php

namespace App\Dsl;

use App\Models\FeatureConfig;

/**
 * Cost model: score = Σ(operator_weight × row_estimate × window_multiplier)
 *
 * Tiers:
 *   0–20    → low    (auto-approve)
 *   21–60   → medium (auto-approve + log)
 *   61–150  → high   (user cost acknowledgment required)
 *   151+    → rejected
 */
class CostModel
{
    public const TIERS = [
        20  => 'low',
        60  => 'medium',
        150 => 'high',
    ];

    public function estimate(string $dataSource, array $pipeline, string $tenantId): array
    {
        $rowEstimate = $this->estimateRows($dataSource, $tenantId, $pipeline);
        $score       = 0;

        foreach ($pipeline as $step) {
            $op     = $step['op'];
            $weight = DslDefinition::OPERATOR_WEIGHTS[$op] ?? 1;

            $windowMultiplier = 1.0;
            if ($op === 'moving_avg' && isset($step['window'])) {
                $days             = $this->parseWindowDays($step['window']) ?? 7;
                $windowMultiplier = $days / 7;
            }

            $score += $weight * $rowEstimate * $windowMultiplier;
        }

        $tier = $this->scoreTier((int) $score);

        return [
            'score'          => (int) $score,
            'tier'           => $tier,
            'estimated_rows' => $rowEstimate,
            'computed_at'    => now()->toIso8601String(),
        ];
    }

    public function scoreTier(int $score): string
    {
        if ($score <= 20)  return 'low';
        if ($score <= 60)  return 'medium';
        if ($score <= 150) return 'high';

        return 'rejected';
    }

    private function estimateRows(string $dataSource, string $tenantId, array $pipeline): int
    {
        // Build a rough row estimate by checking if any filter narrows the dataset.
        // In production this would run a COUNT(*) query with tenant + filter predicates.
        // Here we use a heuristic: base count with per-filter reduction.
        $base = $this->baseRowCount($dataSource, $tenantId);

        foreach ($pipeline as $step) {
            if ($step['op'] === 'filter') {
                // Estimate each filter reduces by 30%
                $base = (int) ($base * 0.7);
            }
        }

        return max($base, 1);
    }

    private function baseRowCount(string $dataSource, string $tenantId): int
    {
        // Placeholder — in production, run: SELECT COUNT(*) WHERE tenant_id = ?
        // Returning a conservative 365 rows (1 year of daily data) as default
        return 365;
    }

    private function parseWindowDays(string $window): ?int
    {
        if (preg_match('/^(\d+)d$/', $window, $m)) return (int) $m[1];
        if (preg_match('/^(\d+)w$/', $window, $m)) return (int) $m[1] * 7;

        return null;
    }
}

<?php

namespace Modules\GraftAI\Dsl;

/**
 * Computes a canonical pipeline signature (sha256) based on the shape
 * of the pipeline — operator sequence + data source category.
 * Field *values* are stripped so that two tenants with the same
 * operator structure but different filter values produce the same signature.
 */
class PipelineSignature
{
    public static function compute(string $dataSource, array $pipeline): string
    {
        $canonical = [
            'data_source' => $dataSource,
            'ops'         => array_map(fn($step) => self::canonicalizeStep($step), $pipeline),
        ];

        ksort($canonical);
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    private static function canonicalizeStep(array $step): array
    {
        $op = $step['op'];

        // Keep structural keys (enums, window size) but strip concrete values
        return match ($op) {
            'filter' => [
                'op'      => 'filter',
                'op_type' => $step['op_type'],
                // strip: field, value
            ],
            'group_by' => [
                'op'       => 'group_by',
                'truncate' => $step['truncate'] ?? null,
            ],
            'aggregate' => [
                'op'       => 'aggregate',
                'function' => $step['function'],
            ],
            'moving_avg' => [
                'op'     => 'moving_avg',
                'window' => $step['window'],
            ],
            'compare' => [
                'op'       => 'compare',
                'type'     => $step['type'],
                'baseline' => $step['baseline'] ?? 'previous_window',
            ],
            'sort' => [
                'op'        => 'sort',
                'direction' => $step['direction'],
            ],
            default => ['op' => $op],
        };
    }
}

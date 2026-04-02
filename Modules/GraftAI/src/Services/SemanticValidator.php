<?php

namespace GraftAI\Services;

use GraftAI\Dsl\DslDefinition;

/**
 * Semantic Validator — re-reads the DSL config and produces a
 * plain-language summary for user confirmation.
 *
 * This is independent of the AI generator: if the AI generated a
 * dangerous config, the user sees what it *actually does* before confirming.
 */
class SemanticValidator
{
    public function summarize(array $config): string
    {
        $parts = [];

        // Schedule summary
        if (! empty($config['schedule'])) {
            $parts[] = $this->describeSchedule($config['schedule']);
        }

        // Data source
        $parts[] = "on {$config['data_source']}";

        // Pipeline steps
        foreach ($config['pipeline'] as $step) {
            $parts[] = $this->describeStep($step);
        }

        // Action
        if (! empty($config['action'])) {
            $parts[] = $this->describeAction($config['action']);
        }

        return implode(' → ', array_filter($parts)) . '.';
    }

    private function describeSchedule(array $schedule): string
    {
        $expr     = $schedule['expression'] ?? '';
        $timezone = $schedule['timezone'] ?? 'UTC';

        $readableMap = [
            '0 8 * * *'   => 'Every day at 8am',
            '0 * * * *'   => 'Every hour',
            '* * * * *'   => 'Every minute',
            '0 0 * * *'   => 'Every day at midnight',
            '0 0 * * 1'   => 'Every Monday at midnight',
            '0 12 * * *'  => 'Every day at noon',
        ];

        $readable = $readableMap[$expr] ?? "On schedule ({$expr})";

        return "{$readable} [{$timezone}]";
    }

    private function describeStep(array $step): string
    {
        return match ($step['op']) {
            'filter' => sprintf(
                'filter where %s %s %s',
                $step['field'],
                $step['op_type'],
                is_array($step['value']) ? '[' . implode(', ', $step['value']) . ']' : $step['value']
            ),
            'group_by' => sprintf(
                'group by %s%s',
                $step['field'],
                isset($step['truncate']) ? " (by {$step['truncate']})" : ''
            ),
            'aggregate' => sprintf(
                'compute %s of %s%s',
                $step['function'],
                $step['metric'],
                isset($step['alias']) ? " as {$step['alias']}" : ''
            ),
            'moving_avg' => sprintf(
                'compute %s-window moving average of %s%s',
                $step['window'],
                $step['metric'],
                isset($step['min_periods']) ? " (min {$step['min_periods']} periods)" : ''
            ),
            'compare' => sprintf(
                'alert if %s by ≥%s%%%s',
                str_replace('_', ' ', $step['type']),
                $step['threshold'],
                isset($step['baseline']) ? " vs {$step['baseline']}" : ''
            ),
            'sort' => sprintf(
                'sort by %s %s%s',
                $step['field'],
                $step['direction'],
                isset($step['limit']) ? " (top {$step['limit']})" : ''
            ),
            default => "step: {$step['op']}",
        };
    }

    private function describeAction(array $action): string
    {
        $type = $action['type'] ?? 'notification';

        if ($type === 'notification') {
            $channel    = $action['channel'] ?? 'in_app';
            $recipients = $action['recipients'] ?? 'tenant_owner';
            return "send {$channel} notification to {$recipients}";
        }

        if ($type === 'webhook') {
            return 'call webhook';
        }

        return "trigger {$type}";
    }
}

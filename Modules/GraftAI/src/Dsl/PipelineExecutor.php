<?php

namespace GraftAI\Dsl;

use Illuminate\Support\Collection;

/**
 * Deterministic DSL pipeline executor.
 *
 * Executes a validated pipeline config against a data set.
 * Each operator transforms the collection in order.
 * - Null propagates through; zero rows → no action.
 * - Runtime limit is enforced by the Celery-equivalent (Laravel Job timeout).
 */
class PipelineExecutor
{
    public const MAX_OUTPUT_ROWS = 1000;

    /**
     * @param  array  $pipeline  Validated DSL pipeline steps
     * @param  Collection  $data  The raw data set (collection of associative arrays)
     * @return array{rows: array, rows_scanned: int, action_triggered: bool}
     */
    public function execute(array $pipeline, Collection $data): array
    {
        $rowsScanned    = $data->count();
        $actionTriggered = false;
        $result         = $data;

        foreach ($pipeline as $step) {
            $result = $this->applyStep($step, $result);

            if ($result === null) {
                $result = collect();
            }
        }

        // Cap output
        $rows = $result->take(self::MAX_OUTPUT_ROWS)->values()->all();

        $hasCompare    = collect($pipeline)->contains(fn($s) => $s['op'] === 'compare');
        $actionTriggered = $hasCompare ? $result->isNotEmpty() : false;

        return [
            'rows'            => $rows,
            'rows_scanned'    => $rowsScanned,
            'action_triggered' => $actionTriggered,
        ];
    }

    private function applyStep(array $step, Collection $data): Collection
    {
        return match ($step['op']) {
            'filter'     => $this->applyFilter($step, $data),
            'group_by'   => $this->applyGroupBy($step, $data),
            'aggregate'  => $this->applyAggregate($step, $data),
            'moving_avg' => $this->applyMovingAvg($step, $data),
            'compare'    => $this->applyCompare($step, $data),
            'sort'       => $this->applySort($step, $data),
            default      => $data,
        };
    }

    private function applyFilter(array $step, Collection $data): Collection
    {
        $field  = $step['field'];
        $opType = $step['op_type'];
        $value  = $step['value'];

        return $data->filter(function ($row) use ($field, $opType, $value) {
            $v = $row[$field] ?? null;

            return match ($opType) {
                'eq'     => $v == $value,
                'neq'    => $v != $value,
                'gt'     => $v > $value,
                'gte'    => $v >= $value,
                'lt'     => $v < $value,
                'lte'    => $v <= $value,
                'in'     => in_array($v, (array) $value, false),
                'not_in' => ! in_array($v, (array) $value, false),
                default  => false,
            };
        })->values();
    }

    private function applyGroupBy(array $step, Collection $data): Collection
    {
        $field    = $step['field'];
        $truncate = $step['truncate'] ?? null;

        return $data->groupBy(function ($row) use ($field, $truncate) {
            $val = $row[$field] ?? null;

            if ($truncate && $val) {
                return match ($truncate) {
                    'day'   => date('Y-m-d', strtotime((string) $val)),
                    'week'  => date('Y-W', strtotime((string) $val)),
                    'month' => date('Y-m', strtotime((string) $val)),
                    default => $val,
                };
            }

            return $val;
        });
    }

    private function applyAggregate(array $step, Collection $groups): Collection
    {
        $metric   = $step['metric'];
        $function = $step['function'];
        $alias    = $step['alias'] ?? $metric;

        if ($groups->first() instanceof Collection) {
            return $groups->map(function ($group, $groupKey) use ($metric, $function, $alias) {
                $values = $group->pluck($metric)->filter()->values();
                $agg    = $this->computeAggregate($values, $function);

                return array_merge(
                    $group->first() ?? [],
                    ['__group_key' => $groupKey, $alias => $agg]
                );
            })->values();
        }

        $values = $groups->pluck($metric)->filter()->values();
        $agg    = $this->computeAggregate($values, $function);

        return collect([[$alias => $agg]]);
    }

    private function computeAggregate(Collection $values, string $function): mixed
    {
        if ($values->isEmpty()) return null;

        return match ($function) {
            'sum'    => $values->sum(),
            'avg'    => $values->avg(),
            'min'    => $values->min(),
            'max'    => $values->max(),
            'count'  => $values->count(),
            'median' => $this->median($values),
            default  => null,
        };
    }

    private function median(Collection $values): float|int|null
    {
        $sorted = $values->sort()->values();
        $count  = $sorted->count();

        if ($count === 0) return null;

        $mid = (int) floor($count / 2);

        return $count % 2 === 0
            ? ($sorted[$mid - 1] + $sorted[$mid]) / 2
            : $sorted[$mid];
    }

    private function applyMovingAvg(array $step, Collection $data): Collection
    {
        $metric     = $step['metric'];
        $windowDays = $this->parseWindowDays($step['window']);
        $minPeriods = $step['min_periods'] ?? 1;
        $alias      = '__moving_avg_' . $metric;

        if ($data->first() instanceof Collection) {
            return $data->map(fn($group) => $this->computeMovingAvgOnGroup($group, $metric, $windowDays, $minPeriods, $alias))
                ->flatten(1)
                ->values();
        }

        return $this->computeMovingAvgOnGroup($data, $metric, $windowDays, $minPeriods, $alias);
    }

    private function computeMovingAvgOnGroup(Collection $group, string $metric, int $windowDays, int $minPeriods, string $alias): Collection
    {
        $rows = $group->values()->all();

        foreach ($rows as $i => &$row) {
            $start  = max(0, $i - $windowDays + 1);
            $window = array_slice($rows, $start, $i - $start + 1);
            $values = array_column($window, $metric);
            $values = array_filter($values, fn($v) => $v !== null);

            if (count($values) < $minPeriods) {
                $row[$alias] = null;
            } else {
                $row[$alias] = array_sum($values) / count($values);
            }
        }

        return collect($rows);
    }

    private function applyCompare(array $step, Collection $data): Collection
    {
        $type      = $step['type'];
        $threshold = (float) $step['threshold'];
        $baseline  = $step['baseline'] ?? 'previous_window';
        $fixedValue = $step['fixed_value'] ?? null;

        $metricKey = $this->detectMovingAvgAlias($data) ?? $this->detectLastNumericKey($data);

        return $data->filter(function ($row) use ($type, $threshold, $baseline, $fixedValue, $metricKey) {
            $current = $row[$metricKey] ?? null;

            if ($current === null) return false;

            $base = match ($baseline) {
                'fixed_value'    => (float) $fixedValue,
                'previous_window' => $row['__prev_window'] ?? null,
                'rolling_mean'   => $row['__rolling_mean'] ?? null,
                default          => null,
            };

            if ($base === null || $base == 0) return false;

            return match ($type) {
                'percent_drop'    => (($base - $current) / $base * 100) >= $threshold,
                'percent_rise'    => (($current - $base) / $base * 100) >= $threshold,
                'absolute_drop'   => ($base - $current) >= $threshold,
                'absolute_rise'   => ($current - $base) >= $threshold,
                'threshold_cross' => $current >= $threshold,
                default           => false,
            };
        })->values();
    }

    private function applySort(array $step, Collection $data): Collection
    {
        $field     = $step['field'];
        $direction = $step['direction'];
        $limit     = min($step['limit'] ?? self::MAX_OUTPUT_ROWS, self::MAX_OUTPUT_ROWS);

        $sorted = $direction === 'asc'
            ? $data->sortBy($field)
            : $data->sortByDesc($field);

        return $sorted->take($limit)->values();
    }

    private function detectMovingAvgAlias(Collection $data): ?string
    {
        $first = $data->first();
        if (! is_array($first)) return null;

        foreach (array_keys($first) as $key) {
            if (str_starts_with((string) $key, '__moving_avg_')) return $key;
        }

        return null;
    }

    private function detectLastNumericKey(Collection $data): ?string
    {
        $first = $data->first();
        if (! is_array($first)) return null;

        foreach (array_reverse(array_keys($first)) as $key) {
            if (str_starts_with((string) $key, '__')) continue;
            if (is_numeric($first[$key])) return $key;
        }

        return null;
    }

    private function parseWindowDays(string $window): int
    {
        if (preg_match('/^(\d+)d$/', $window, $m)) return (int) $m[1];
        if (preg_match('/^(\d+)w$/', $window, $m)) return (int) $m[1] * 7;

        return 7;
    }
}

<?php

namespace GraftAI\Dsl;

/**
 * Canonical definition of DSL operators, rules, and constraints.
 * All validation and execution components source from here.
 */
class DslDefinition
{
    public const CURRENT_VERSION = '1.0';

    public const OPERATORS = [
        'filter', 'group_by', 'aggregate', 'moving_avg', 'compare', 'sort',
    ];

    public const OPERATOR_WEIGHTS = [
        'filter'     => 1,
        'sort'       => 1,
        'group_by'   => 2,
        'aggregate'  => 3,
        'moving_avg' => 5,
        'compare'    => 2,
    ];

    public const FILTER_OP_TYPES = [
        'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in',
    ];

    public const AGGREGATE_FUNCTIONS = [
        'sum', 'avg', 'min', 'max', 'count', 'median',
    ];

    public const COMPARE_TYPES = [
        'percent_drop', 'percent_rise', 'absolute_drop', 'absolute_rise', 'threshold_cross',
    ];

    public const COMPARE_BASELINES = [
        'previous_window', 'rolling_mean', 'fixed_value',
    ];

    public const SORT_DIRECTIONS = ['asc', 'desc'];

    public const GROUP_BY_TRUNCATIONS = ['day', 'week', 'month'];

    public const MAX_PIPELINE_LENGTH = 8;
    public const MIN_PIPELINE_LENGTH = 1;
    public const MAX_MOVING_AVG_WINDOW_DAYS = 90;
    public const MAX_FILTER_ARRAY_LENGTH = 50;
    public const MAX_SORT_LIMIT = 1000;

    /** Operator schemas indexed by operator name */
    public static function schema(string $op): array
    {
        return match ($op) {
            'filter' => [
                'required' => ['field', 'op_type', 'value'],
                'optional' => [],
            ],
            'group_by' => [
                'required' => ['field'],
                'optional' => ['truncate'],
            ],
            'aggregate' => [
                'required' => ['metric', 'function'],
                'optional' => ['alias'],
            ],
            'moving_avg' => [
                'required' => ['metric', 'window'],
                'optional' => ['min_periods'],
            ],
            'compare' => [
                'required' => ['type', 'threshold'],
                'optional' => ['baseline', 'fixed_value'],
            ],
            'sort' => [
                'required' => ['field', 'direction'],
                'optional' => ['limit'],
            ],
            default => throw new \InvalidArgumentException("Unknown operator: {$op}"),
        };
    }
}

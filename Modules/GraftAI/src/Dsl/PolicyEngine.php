<?php

namespace GraftAI\Dsl;

use GraftAI\Models\CapabilityRegistry;
use GraftAI\Models\FeatureConfig;
use GraftAI\Models\FeatureExecution;
use GraftAI\Models\Tenant;
use GraftAI\Models\TenantBudget;

/**
 * Two-stage policy engine.
 *
 * Stage 1 — Creation time (synchronous, before save).
 * Stage 2 — Execution time (inside the queue worker, before running pipeline).
 */
class PolicyEngine
{
    // ──────────────────────────────────────────────
    //  Stage 1 — Creation time
    // ──────────────────────────────────────────────

    /**
     * @return array{ok: bool, errors: array, trust_tier: int|null, cost_estimate: array|null}
     */
    public function stage1(array $config, Tenant $tenant): array
    {
        $errors = [];

        // 1. DSL schema valid for declared dsl_version
        $schemaErrors = $this->validateSchema($config);
        if ($schemaErrors) {
            return ['ok' => false, 'errors' => $schemaErrors, 'trust_tier' => null, 'cost_estimate' => null];
        }

        $dataSource = $config['data_source'];
        $pipeline   = $config['pipeline'];

        // 2. data_source in tenant's granted capabilities
        $allowedCapabilities = CapabilityRegistry::activeCapabilityNames();
        if (! in_array($dataSource, $allowedCapabilities, true)) {
            $errors[] = "data_source '{$dataSource}' is not an active capability.";
        }

        // 3. All field references in capability allowlist
        $allowedFields = CapabilityRegistry::fieldAllowlistFor($dataSource);
        foreach ($pipeline as $i => $step) {
            $fieldErrors = $this->validateFieldReferences($step, $allowedFields, $i);
            $errors = array_merge($errors, $fieldErrors);
        }

        // 4. Pipeline ordering rules
        $orderErrors = $this->validatePipelineOrder($pipeline);
        $errors = array_merge($errors, $orderErrors);

        // 5. Enum values
        $enumErrors = $this->validateEnums($pipeline);
        $errors = array_merge($errors, $enumErrors);

        // 6. Cron expression
        if (isset($config['schedule']['expression'])) {
            if (! $this->isValidCron($config['schedule']['expression'])) {
                $errors[] = 'schedule.expression is not a valid cron expression.';
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'trust_tier' => null, 'cost_estimate' => null];
        }

        // 7. Cost score
        $costModel    = new CostModel();
        $costEstimate = $costModel->estimate($dataSource, $pipeline, $tenant->id);

        if ($costEstimate['tier'] === 'rejected') {
            return [
                'ok'           => false,
                'errors'       => ['Cost score exceeds limit. Suggest simplifying the pipeline.'],
                'trust_tier'   => null,
                'cost_estimate' => $costEstimate,
            ];
        }

        // 8. Trust tier
        $trustTier = $this->computeTrustTier($config, $tenant);

        // 9. Validate schedule frequency (max 4 executions/day)
        if (isset($config['schedule']['expression'])) {
            $dailyCount = $this->estimateDailyExecutions($config['schedule']['expression']);
            if ($dailyCount > 4) {
                $errors[] = 'Schedule exceeds maximum of 4 executions per day per feature.';
            }
        }

        // Max scheduled features per tenant
        $scheduledCount = FeatureConfig::where('tenant_id', $tenant->id)
            ->whereNotNull('schedule')
            ->where('status', 'active')
            ->count();
        if ($scheduledCount >= 20) {
            $errors[] = 'Tenant has reached the maximum of 20 scheduled features.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'trust_tier' => $trustTier, 'cost_estimate' => $costEstimate];
        }

        return [
            'ok'           => true,
            'errors'       => [],
            'trust_tier'   => $trustTier,
            'cost_estimate' => $costEstimate,
        ];
    }

    // ──────────────────────────────────────────────
    //  Stage 2 — Execution time
    // ──────────────────────────────────────────────

    /**
     * @return array{ok: bool, reason: string|null}
     */
    public function stage2(FeatureConfig $feature, string $executionTenantId): array
    {
        $tenant = $feature->tenant;

        // 1. Tenant active
        if (! $tenant->isActive()) {
            return ['ok' => false, 'reason' => 'Tenant is not active.'];
        }

        // 2. Feature status still active
        if (! $feature->isActive()) {
            return ['ok' => false, 'reason' => 'Feature is not active.'];
        }

        // 3. tenant_id injected from execution context — never from config
        if ($feature->tenant_id !== $executionTenantId) {
            return ['ok' => false, 'reason' => 'Tenant ID mismatch between feature and execution context.'];
        }

        // 4. Budget check
        $budget = TenantBudget::getOrCreate($tenant->id);
        if ($budget->isHalted()) {
            return ['ok' => false, 'reason' => 'Tenant monthly execution budget exhausted.'];
        }

        // 5. Concurrent executions
        $concurrent = FeatureExecution::where('tenant_id', $tenant->id)
            ->where('status', 'running')
            ->count();
        if ($concurrent >= 5) {
            return ['ok' => false, 'reason' => 'Maximum concurrent executions (5) reached for tenant.'];
        }

        return ['ok' => true, 'reason' => null];
    }

    // ──────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────

    private function validateSchema(array $config): array
    {
        $errors = [];

        // Required top-level fields
        foreach (['data_source', 'pipeline', 'action', 'type'] as $field) {
            if (empty($config[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        if ($errors) {
            return $errors;
        }

        $pipeline = $config['pipeline'];

        if (! is_array($pipeline) || count($pipeline) < DslDefinition::MIN_PIPELINE_LENGTH) {
            $errors[] = 'Pipeline must have at least 1 operator.';
        }

        if (count($pipeline) > DslDefinition::MAX_PIPELINE_LENGTH) {
            $errors[] = 'Pipeline exceeds maximum of 8 operators.';
        }

        foreach ($pipeline as $i => $step) {
            if (empty($step['op'])) {
                $errors[] = "Step {$i}: missing 'op' field.";
                continue;
            }

            if (! in_array($step['op'], DslDefinition::OPERATORS, true)) {
                $errors[] = "Step {$i}: unknown operator '{$step['op']}'.";
                continue;
            }

            $schema = DslDefinition::schema($step['op']);
            foreach ($schema['required'] as $req) {
                if (! array_key_exists($req, $step)) {
                    $errors[] = "Step {$i} ({$step['op']}): missing required field '{$req}'.";
                }
            }

            // moving_avg window max
            if ($step['op'] === 'moving_avg' && isset($step['window'])) {
                $days = $this->parseWindowDays($step['window']);
                if ($days === null || $days > DslDefinition::MAX_MOVING_AVG_WINDOW_DAYS) {
                    $errors[] = "Step {$i}: moving_avg window must be Nd or Nw, max 90d.";
                }
            }

            // filter in/not_in array constraints
            if ($step['op'] === 'filter' && in_array($step['op_type'] ?? '', ['in', 'not_in'], true)) {
                if (! is_array($step['value'] ?? null) || count($step['value']) === 0) {
                    $errors[] = "Step {$i}: filter in/not_in requires a non-empty array value.";
                } elseif (count($step['value']) > DslDefinition::MAX_FILTER_ARRAY_LENGTH) {
                    $errors[] = "Step {$i}: filter in/not_in array exceeds max length of 50.";
                }
            }

            // sort limit cap
            if ($step['op'] === 'sort' && isset($step['limit'])) {
                if ((int) $step['limit'] > DslDefinition::MAX_SORT_LIMIT) {
                    $errors[] = "Step {$i}: sort limit exceeds maximum of 1000.";
                }
            }

            // compare fixed_value baseline requires fixed_value
            if ($step['op'] === 'compare' && ($step['baseline'] ?? '') === 'fixed_value') {
                if (! isset($step['fixed_value'])) {
                    $errors[] = "Step {$i}: compare with baseline=fixed_value requires 'fixed_value'.";
                }
            }
        }

        return $errors;
    }

    private function validateFieldReferences(array $step, array $allowedFields, int $index): array
    {
        $errors = [];
        $fieldsToCheck = [];

        if ($step['op'] === 'filter')      $fieldsToCheck[] = $step['field'] ?? null;
        if ($step['op'] === 'group_by')    $fieldsToCheck[] = $step['field'] ?? null;
        if ($step['op'] === 'aggregate')   $fieldsToCheck[] = $step['metric'] ?? null;
        if ($step['op'] === 'moving_avg')  $fieldsToCheck[] = $step['metric'] ?? null;
        if ($step['op'] === 'sort')        $fieldsToCheck[] = $step['field'] ?? null;

        foreach ($fieldsToCheck as $field) {
            if ($field === null) continue;
            if (! in_array($field, $allowedFields, true)) {
                $errors[] = "Step {$index} ({$step['op']}): field '{$field}' is not in the capability allowlist.";
            }
        }

        return $errors;
    }

    private function validatePipelineOrder(array $pipeline): array
    {
        $errors = [];
        $ops    = array_column($pipeline, 'op');

        // compare must be terminal
        $comparePositions = array_keys(array_filter($ops, fn($o) => $o === 'compare'));
        foreach ($comparePositions as $pos) {
            if ($pos !== count($ops) - 1) {
                $errors[] = "'compare' operator must be the terminal (last) operator.";
            }
        }

        // group_by must precede aggregate or moving_avg
        $groupByPos    = array_search('group_by', $ops);
        $aggregatePos  = array_search('aggregate', $ops);
        $movingAvgPos  = array_search('moving_avg', $ops);

        if ($groupByPos !== false) {
            if ($groupByPos === count($ops) - 1) {
                $errors[] = "'group_by' cannot be a terminal operator — must be followed by aggregate or moving_avg.";
            }
        }

        if ($aggregatePos !== false && $groupByPos !== false && $groupByPos > $aggregatePos) {
            $errors[] = "'group_by' must precede 'aggregate'.";
        }

        if ($movingAvgPos !== false && $groupByPos !== false && $groupByPos > $movingAvgPos) {
            $errors[] = "'group_by' must precede 'moving_avg'.";
        }

        return $errors;
    }

    private function validateEnums(array $pipeline): array
    {
        $errors = [];

        foreach ($pipeline as $i => $step) {
            switch ($step['op'] ?? '') {
                case 'filter':
                    if (isset($step['op_type']) && ! in_array($step['op_type'], DslDefinition::FILTER_OP_TYPES, true)) {
                        $errors[] = "Step {$i}: invalid filter op_type '{$step['op_type']}'.";
                    }
                    break;
                case 'group_by':
                    if (isset($step['truncate']) && ! in_array($step['truncate'], DslDefinition::GROUP_BY_TRUNCATIONS, true)) {
                        $errors[] = "Step {$i}: invalid group_by truncate '{$step['truncate']}'.";
                    }
                    break;
                case 'aggregate':
                    if (isset($step['function']) && ! in_array($step['function'], DslDefinition::AGGREGATE_FUNCTIONS, true)) {
                        $errors[] = "Step {$i}: invalid aggregate function '{$step['function']}'.";
                    }
                    break;
                case 'compare':
                    if (isset($step['type']) && ! in_array($step['type'], DslDefinition::COMPARE_TYPES, true)) {
                        $errors[] = "Step {$i}: invalid compare type '{$step['type']}'.";
                    }
                    if (isset($step['baseline']) && ! in_array($step['baseline'], DslDefinition::COMPARE_BASELINES, true)) {
                        $errors[] = "Step {$i}: invalid compare baseline '{$step['baseline']}'.";
                    }
                    break;
                case 'sort':
                    if (isset($step['direction']) && ! in_array($step['direction'], DslDefinition::SORT_DIRECTIONS, true)) {
                        $errors[] = "Step {$i}: invalid sort direction '{$step['direction']}'.";
                    }
                    break;
            }
        }

        return $errors;
    }

    private function computeTrustTier(array $config, Tenant $tenant): int
    {
        $ops = array_column($config['pipeline'], 'op');

        $hasAggregation    = in_array('aggregate', $ops, true) || in_array('moving_avg', $ops, true);
        $hasSchedule       = ! empty($config['schedule']);
        $hasExternalAction = ($config['action']['type'] ?? '') === 'webhook';

        if ($hasExternalAction) return 3;
        if ($hasAggregation || $hasSchedule) return 2;

        return 1;
    }

    private function isValidCron(string $expression): bool
    {
        $parts = preg_split('/\s+/', trim($expression));

        return count($parts) === 5;
    }

    private function estimateDailyExecutions(string $expression): int
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) return 0;

        [$minute, $hour] = $parts;

        $minuteCount = $minute === '*' ? 60 : (str_contains($minute, ',') ? count(explode(',', $minute)) : 1);
        $hourCount   = $hour === '*' ? 24 : (str_contains($hour, ',') ? count(explode(',', $hour)) : 1);

        return $minuteCount * $hourCount;
    }

    private function parseWindowDays(string $window): ?int
    {
        if (preg_match('/^(\d+)d$/', $window, $m)) return (int) $m[1];
        if (preg_match('/^(\d+)w$/', $window, $m)) return (int) $m[1] * 7;

        return null;
    }
}

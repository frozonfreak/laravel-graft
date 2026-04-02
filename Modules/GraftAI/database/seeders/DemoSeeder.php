<?php

namespace GraftAI\Database\Seeders;

use GraftAI\Dsl\PipelineSignature;
use GraftAI\Models\AuditEvent;
use GraftAI\Models\CapabilityRegistry;
use GraftAI\Models\EvolutionEvent;
use GraftAI\Models\ExecutionSignal;
use GraftAI\Models\FeatureConfig;
use GraftAI\Models\FeatureExecution;
use GraftAI\Models\FeatureSnapshot;
use GraftAI\Models\PromotionCandidate;
use GraftAI\Models\Tenant;
use GraftAI\Models\TenantBudget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CapabilityRegistrySeeder::class);

        // ── Tenants ──────────────────────────────────────────────────────────

        $tenants = [
            ['name' => 'Green Valley Farms',  'slug' => 'green-valley'],
            ['name' => 'Sunrise Agro Co.',    'slug' => 'sunrise-agro'],
            ['name' => 'Bharat Kisan Hub',    'slug' => 'bharat-kisan'],
            ['name' => 'AgriLink Solutions',  'slug' => 'agrilink'],
            ['name' => 'Krishi Mitra',        'slug' => 'krishi-mitra'],
        ];

        $createdTenants = [];
        foreach ($tenants as $data) {
            $tenant = Tenant::firstOrCreate(['slug' => $data['slug']], array_merge($data, [
                'status'            => 'active',
                'evolution_settings' => [
                    'contribute_to_pattern_detection'        => true,
                    'receive_promoted_feature_notifications' => true,
                    'auto_migrate_promoted_configs'          => false,
                ],
            ]));

            TenantBudget::getOrCreate($tenant->id);
            $createdTenants[] = $tenant;
        }

        // ── Features per tenant ───────────────────────────────────────────────

        $featureDefs = [
            // The "price drop alert" pattern — used by all 5 tenants
            [
                'label'       => 'Clove Price Drop Alert',
                'type'        => 'alert',
                'data_source' => 'crop_prices',
                'pipeline'    => [
                    ['op' => 'filter',     'field' => 'crop',         'op_type' => 'eq', 'value' => 'clove'],
                    ['op' => 'group_by',   'field' => 'date',         'truncate' => 'day'],
                    ['op' => 'moving_avg', 'metric' => 'modal_price', 'window' => '7d', 'min_periods' => 3],
                    ['op' => 'compare',    'type' => 'percent_drop',  'threshold' => 10],
                ],
                'action'   => ['type' => 'notification', 'channel' => 'sms',   'recipients' => 'tenant_owner'],
                'schedule' => ['type' => 'cron', 'expression' => '0 8 * * *', 'timezone' => 'Asia/Kolkata'],
                'executions' => 120,
            ],
            // Same shape for a different crop — counts toward same signature
            [
                'label'       => 'Turmeric Price Drop Alert',
                'type'        => 'alert',
                'data_source' => 'crop_prices',
                'pipeline'    => [
                    ['op' => 'filter',     'field' => 'crop',         'op_type' => 'eq', 'value' => 'turmeric'],
                    ['op' => 'group_by',   'field' => 'date',         'truncate' => 'day'],
                    ['op' => 'moving_avg', 'metric' => 'modal_price', 'window' => '7d', 'min_periods' => 3],
                    ['op' => 'compare',    'type' => 'percent_drop',  'threshold' => 8],
                ],
                'action'   => ['type' => 'notification', 'channel' => 'email', 'recipients' => 'tenant_owner'],
                'schedule' => ['type' => 'cron', 'expression' => '0 8 * * *', 'timezone' => 'Asia/Kolkata'],
                'executions' => 90,
            ],
            // Different pattern — top arrivals sort
            [
                'label'       => 'Top Market Arrivals Report',
                'type'        => 'report',
                'data_source' => 'crop_prices',
                'pipeline'    => [
                    ['op' => 'filter', 'field' => 'crop',         'op_type' => 'eq', 'value' => 'wheat'],
                    ['op' => 'sort',   'field' => 'arrivals_qty', 'direction' => 'desc', 'limit' => 10],
                ],
                'action'   => ['type' => 'notification', 'channel' => 'email', 'recipients' => 'tenant_owner'],
                'schedule' => ['type' => 'cron', 'expression' => '0 7 * * 1', 'timezone' => 'Asia/Kolkata'],
                'executions' => 20,
            ],
        ];

        $allFeatures = [];

        foreach ($createdTenants as $tenantIndex => $tenant) {
            foreach ($featureDefs as $defIndex => $def) {
                if ($defIndex === 2 && $tenantIndex > 2) continue;

                $signature = PipelineSignature::compute($def['data_source'], $def['pipeline']);

                $costEstimate = [
                    'score'          => 42,
                    'tier'           => 'medium',
                    'estimated_rows' => 365,
                    'computed_at'    => now()->toIso8601String(),
                ];

                $feature = FeatureConfig::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'pipeline_signature' => $signature],
                    [
                        'dsl_version'            => '1.0',
                        'feature_version'        => 1,
                        'lifecycle_stage'        => 'sandbox',
                        'type'                   => $def['type'],
                        'data_source'            => $def['data_source'],
                        'pipeline'               => $def['pipeline'],
                        'action'                 => $def['action'],
                        'schedule'               => $def['schedule'],
                        'status'                 => 'active',
                        'trust_tier'             => 2,
                        'cost_estimate'          => $costEstimate,
                        'pipeline_signature'     => $signature,
                        'contributes_to_evolution' => true,
                        'promoted_to_core'       => false,
                        'created_by'             => 'ai',
                        'last_executed_at'       => now()->subHours(rand(1, 24)),
                    ]
                );

                $allFeatures[] = ['feature' => $feature, 'def' => $def];

                $executionCount = (int) ($def['executions'] * (0.8 + 0.4 * ($tenantIndex / count($createdTenants))));
                for ($i = 0; $i < min($executionCount, 30); $i++) {
                    $success = rand(1, 10) > 1;
                    $daysAgo = rand(1, 90);

                    $execution = FeatureExecution::create([
                        'feature_id'   => $feature->id,
                        'tenant_id'    => $tenant->id,
                        'status'       => $success ? 'success' : 'failure',
                        'started_at'   => now()->subDays($daysAgo)->setHour(8),
                        'completed_at' => now()->subDays($daysAgo)->setHour(8)->addMilliseconds(rand(200, 800)),
                        'execution_ms' => rand(200, 800),
                        'rows_scanned' => rand(300, 400),
                        'cost_actual'  => 42,
                    ]);

                    if ($success) {
                        ExecutionSignal::create([
                            'feature_id'        => $feature->id,
                            'pipeline_signature' => $signature,
                            'dsl_version'       => '1.0',
                            'data_source'       => $def['data_source'],
                            'execution_outcome' => 'success',
                            'action_triggered'  => rand(0, 3) > 0,
                            'execution_ms'      => $execution->execution_ms,
                            'rows_scanned'      => $execution->rows_scanned,
                            'user_feedback'     => rand(0, 2) > 0 ? 'useful' : null,
                            'emitted_at'        => now()->subDays($daysAgo),
                        ]);
                    }
                }
            }
        }

        // ── Tenant snapshots ─────────────────────────────────────────────────

        foreach ($createdTenants as $tenant) {
            FeatureSnapshot::firstOrCreate(
                ['tenant_id' => $tenant->id, 'label' => 'initial-setup'],
                [
                    'snapshot_type'          => 'tenant',
                    'features'               => FeatureConfig::where('tenant_id', $tenant->id)->get()->toArray(),
                    'dsl_version_at_snapshot' => '1.0',
                    'created_by'             => 'system',
                    'created_at'             => now()->subDays(30),
                ]
            );
        }

        // ── Promotion candidates ──────────────────────────────────────────────

        $promotionSignature = PipelineSignature::compute('crop_prices', [
            ['op' => 'filter',     'field' => 'crop',         'op_type' => 'eq', 'value' => 'clove'],
            ['op' => 'group_by',   'field' => 'date',         'truncate' => 'day'],
            ['op' => 'moving_avg', 'metric' => 'modal_price', 'window' => '7d', 'min_periods' => 3],
            ['op' => 'compare',    'type' => 'percent_drop',  'threshold' => 10],
        ]);

        PromotionCandidate::firstOrCreate(
            ['pipeline_signature' => $promotionSignature],
            [
                'distinct_tenants'    => 5,
                'distinct_features'   => 8,
                'weighted_exec_score' => 620,
                'success_rate'        => 0.93,
                'avg_feedback_score'  => 4.3,
                'risk_tier'           => 'medium',
                'status'              => 'pending',
            ]
        );

        $sortSignature = PipelineSignature::compute('crop_prices', [
            ['op' => 'filter', 'field' => 'crop',         'op_type' => 'eq', 'value' => 'wheat'],
            ['op' => 'sort',   'field' => 'arrivals_qty', 'direction' => 'desc', 'limit' => 10],
        ]);

        PromotionCandidate::firstOrCreate(
            ['pipeline_signature' => $sortSignature],
            [
                'distinct_tenants'    => 3,
                'distinct_features'   => 5,
                'weighted_exec_score' => 215,
                'success_rate'        => 0.97,
                'avg_feedback_score'  => 4.6,
                'risk_tier'           => 'low',
                'status'              => 'approved',
                'reviewed_by'         => 'system:auto',
                'reviewed_at'         => now()->subDays(2),
            ]
        );

        // ── Audit events ─────────────────────────────────────────────────────

        foreach ($createdTenants as $tenant) {
            AuditEvent::create([
                'tenant_id'  => $tenant->id,
                'feature_id' => null,
                'event_type' => 'tenant_created',
                'actor'      => 'system',
                'detail'     => ['slug' => $tenant->slug],
                'created_at' => now()->subDays(rand(30, 90)),
            ]);
        }

        $this->command->info('Demo seed complete: ' . count($createdTenants) . ' tenants, features, signals, and 2 promotion candidates.');
    }
}

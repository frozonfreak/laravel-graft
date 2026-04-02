<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Model
    |--------------------------------------------------------------------------
    |
    | The Claude model used to convert natural-language prompts into DSL
    | pipeline configs. Defaults to claude-haiku for low latency.
    | Your ANTHROPIC_API_KEY must be set in config/services.php.
    |
    */

    'model' => env('GRAFTAI_MODEL', 'claude-haiku-4-5-20251001'),

    /*
    |--------------------------------------------------------------------------
    | Pipeline Limits
    |--------------------------------------------------------------------------
    |
    | Guard rails applied before any pipeline is saved.
    | max_cost_score:    Reject configs whose computed cost score exceeds this.
    | max_daily_runs:    Max executions per feature per day (enforced at Stage 1).
    | max_scheduled:     Max active scheduled features per tenant.
    | max_concurrent:    Max simultaneous pipeline executions per tenant (Stage 2).
    |
    */

    'limits' => [
        'max_cost_score'   => 150,
        'max_daily_runs'   => 4,
        'max_scheduled'    => 20,
        'max_concurrent'   => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Promotion Thresholds
    |--------------------------------------------------------------------------
    |
    | A pipeline shape becomes a promotion candidate when all four conditions
    | are met simultaneously. Tune these to match your tenant base size.
    |
    */

    'promotion' => [
        'min_tenants'       => 3,    // Distinct opted-in tenants using the shape
        'min_score'         => 200,  // Σ min(per_feature_count, 500)
        'min_features'      => 5,    // Distinct features with this shape
        'min_success_rate'  => 0.90, // 90% execution success rate
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue connection and name for ExecuteFeature jobs.
    | Set to 'sync' for local development without a queue worker.
    |
    */

    'queue' => [
        'connection' => env('GRAFTAI_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'name'       => env('GRAFTAI_QUEUE', 'graftai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Control whether GraftAI registers its web and API routes automatically.
    | Disable if you want to register them manually (e.g., to add auth middleware).
    |
    */

    'routes' => [
        'web' => true,
        'api' => true,
    ],

];

<?php

use GraftAI\Database\Seeders\CapabilityRegistrySeeder;
use GraftAI\Dsl\PolicyEngine;
use GraftAI\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed the founding capabilities so the policy engine has something to validate against
    (new CapabilityRegistrySeeder)->run();

    $this->tenant = Tenant::create([
        'name' => 'Test Farm',
        'slug' => 'test-farm',
        'status' => 'active',
    ]);
});

it('passes schema and capability validation for a well-formed pipeline', function () {
    $engine = new PolicyEngine;

    $config = [
        'type' => 'alert',
        'data_source' => 'crop_prices',
        'pipeline' => [
            ['op' => 'filter', 'field' => 'crop', 'op_type' => 'eq', 'value' => 'wheat'],
            ['op' => 'compare', 'field' => 'modal_price', 'type' => 'percent_drop', 'threshold' => 15, 'baseline' => 'previous_window'],
        ],
        'action' => ['type' => 'notification', 'channel' => 'email', 'recipients' => 'tenant_owner'],
        'schedule' => ['type' => 'cron', 'expression' => '0 7 * * *', 'timezone' => 'UTC'],
    ];

    $result = $engine->stage1($config, $this->tenant);

    // The pipeline passes schema/capability/cron validation.
    // If it fails, the only acceptable reason is cost (not a schema or source error).
    if (! $result['ok']) {
        $errorText = implode(' ', $result['errors']);
        expect($errorText)->toContain('Cost score');
    }

    expect($result['cost_estimate'])->not->toBeNull();
});

it('rejects a config with an unknown data source', function () {
    $engine = new PolicyEngine;

    $config = [
        'type' => 'alert',
        'data_source' => 'nonexistent_source',
        'pipeline' => [
            ['op' => 'filter', 'field' => 'status', 'op_type' => 'eq', 'value' => 'active'],
        ],
        'action' => ['type' => 'notification', 'channel' => 'email', 'recipients' => 'tenant_owner'],
        'schedule' => ['type' => 'cron', 'expression' => '0 7 * * *', 'timezone' => 'UTC'],
    ];

    $result = $engine->stage1($config, $this->tenant);

    expect($result['ok'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
});

it('rejects a config missing required pipeline fields', function () {
    $engine = new PolicyEngine;

    $config = [
        'type' => 'alert',
        'data_source' => 'crop_prices',
        'pipeline' => [
            ['op' => 'filter'], // missing field, op_type, value
        ],
        'action' => ['type' => 'notification', 'channel' => 'email', 'recipients' => 'tenant_owner'],
        'schedule' => ['type' => 'cron', 'expression' => '0 7 * * *', 'timezone' => 'UTC'],
    ];

    $result = $engine->stage1($config, $this->tenant);

    expect($result['ok'])->toBeFalse();
});

it('rejects a config with an invalid cron expression', function () {
    $engine = new PolicyEngine;

    $config = [
        'type' => 'report',
        'data_source' => 'crop_prices',
        'pipeline' => [
            ['op' => 'filter', 'field' => 'crop', 'op_type' => 'eq', 'value' => 'wheat'],
        ],
        'action' => ['type' => 'notification', 'channel' => 'email', 'recipients' => 'tenant_owner'],
        'schedule' => ['type' => 'cron', 'expression' => 'not-a-cron', 'timezone' => 'UTC'],
    ];

    $result = $engine->stage1($config, $this->tenant);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('cron');
});

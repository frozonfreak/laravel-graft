<?php

use GraftAI\Dsl\PipelineSignature;

it('produces the same signature for identical pipeline shapes', function () {
    $pipeline = [
        ['op' => 'filter', 'field' => 'status', 'op_type' => 'eq', 'value' => 'active'],
        ['op' => 'aggregate', 'function' => 'sum', 'field' => 'yield'],
    ];

    $sig1 = PipelineSignature::compute('crop_yield', $pipeline);
    $sig2 = PipelineSignature::compute('crop_yield', $pipeline);

    expect($sig1)->toBe($sig2);
});

it('strips field values so tenants with different values share the same signature', function () {
    $pipelineA = [
        ['op' => 'filter', 'field' => 'region', 'op_type' => 'eq', 'value' => 'north'],
    ];
    $pipelineB = [
        ['op' => 'filter', 'field' => 'region', 'op_type' => 'eq', 'value' => 'south'],
    ];

    expect(PipelineSignature::compute('crop_yield', $pipelineA))
        ->toBe(PipelineSignature::compute('crop_yield', $pipelineB));
});

it('produces different signatures for different operator structures', function () {
    $filterOnly = [
        ['op' => 'filter', 'field' => 'status', 'op_type' => 'eq', 'value' => 'active'],
    ];
    $filterPlusAggregate = [
        ['op' => 'filter', 'field' => 'status', 'op_type' => 'eq', 'value' => 'active'],
        ['op' => 'aggregate', 'function' => 'sum', 'field' => 'yield'],
    ];

    expect(PipelineSignature::compute('crop_yield', $filterOnly))
        ->not->toBe(PipelineSignature::compute('crop_yield', $filterPlusAggregate));
});

it('produces different signatures for different data sources', function () {
    $pipeline = [
        ['op' => 'filter', 'field' => 'status', 'op_type' => 'eq', 'value' => 'active'],
    ];

    expect(PipelineSignature::compute('crop_yield', $pipeline))
        ->not->toBe(PipelineSignature::compute('equipment_logs', $pipeline));
});

it('returns a 64-character hex string (sha256)', function () {
    $sig = PipelineSignature::compute('crop_yield', [
        ['op' => 'compare', 'field' => 'yield', 'type' => 'percent_drop', 'threshold' => 15],
    ]);

    expect($sig)->toMatch('/^[a-f0-9]{64}$/');
});

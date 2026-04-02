<?php

use Modules\GraftAI\Dsl\CostModel;

it('returns a complete cost estimate structure', function () {
    $model = new CostModel();
    $result = $model->estimate('crop_yield', [
        ['op' => 'filter', 'field' => 'status', 'op_type' => 'eq', 'value' => 'active'],
    ], 'tenant-1');

    // Base = 365 rows, filter reduces to 255, weight 1 → score 255 (rejected by default)
    expect($result)->toHaveKeys(['score', 'tier', 'estimated_rows', 'computed_at']);
    expect($result['score'])->toBeInt()->toBeGreaterThan(0);
    expect($result['tier'])->toBeIn(['low', 'medium', 'high', 'rejected']);
    expect($result['estimated_rows'])->toBeInt()->toBeGreaterThan(0);
});

it('scores a moving_avg step higher than a filter step', function () {
    $model = new CostModel();

    $filterResult = $model->estimate('crop_yield', [
        ['op' => 'filter', 'field' => 'status', 'op_type' => 'eq', 'value' => 'active'],
    ], 'tenant-1');

    $movingAvgResult = $model->estimate('crop_yield', [
        ['op' => 'moving_avg', 'field' => 'yield', 'window' => '30d'],
    ], 'tenant-1');

    expect($movingAvgResult['score'])->toBeGreaterThan($filterResult['score']);
});

it('returns rejected tier for very expensive pipelines', function () {
    $model = new CostModel();

    $tier = $model->scoreTier(200);

    expect($tier)->toBe('rejected');
});

it('maps score boundaries to correct tiers', function () {
    $model = new CostModel();

    expect($model->scoreTier(0))->toBe('low');
    expect($model->scoreTier(20))->toBe('low');
    expect($model->scoreTier(21))->toBe('medium');
    expect($model->scoreTier(60))->toBe('medium');
    expect($model->scoreTier(61))->toBe('high');
    expect($model->scoreTier(150))->toBe('high');
    expect($model->scoreTier(151))->toBe('rejected');
});

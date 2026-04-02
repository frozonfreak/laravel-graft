<?php

use GraftAI\Http\Controllers\GovernanceDemoController;
use GraftAI\Http\Controllers\TenantDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TenantDemoController::class, 'index']);
Route::delete('/features/{id}/archive', [TenantDemoController::class, 'archive'])
    ->name('features.archive');

Route::get('/governance', [GovernanceDemoController::class, 'index']);

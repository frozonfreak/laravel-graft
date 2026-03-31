<?php

use App\Http\Controllers\Api\FeatureController;
use App\Http\Controllers\Api\GovernanceController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\SnapshotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI-Driven Evolving System — API Routes
|--------------------------------------------------------------------------
|
| All tenant-facing routes require X-Tenant-ID header.
| Governance routes require internal auth (dev team).
|
*/

// ── Tenant: Smart Automations (AI Rules) ─────────────────────────────────

Route::prefix('features')->group(function () {
    // AI → Confirm → Save flow
    Route::post('/generate', [FeatureController::class, 'generate']);  // Step 1: AI generates config
    Route::post('/', [FeatureController::class, 'store']);              // Step 2: User confirms + saves
    Route::get('/', [FeatureController::class, 'index']);
    Route::get('/{id}', [FeatureController::class, 'show']);
    Route::delete('/{id}', [FeatureController::class, 'destroy']);
    Route::post('/{id}/rollback', [FeatureController::class, 'rollback']);
});

// ── Tenant: Snapshots ─────────────────────────────────────────────────────

Route::prefix('snapshots')->group(function () {
    Route::post('/', [SnapshotController::class, 'store']);
    Route::get('/', [SnapshotController::class, 'index']);
    Route::get('/{id}', [SnapshotController::class, 'show']);
});

// ── Tenant: Signal Feedback ───────────────────────────────────────────────

Route::post('/signals/{id}/feedback', [SignalController::class, 'feedback']);

// ── Governance (internal / dev team) ─────────────────────────────────────

Route::prefix('governance')->group(function () {
    Route::get('/candidates', [GovernanceController::class, 'candidates']);
    Route::post('/candidates/{id}/approve', [GovernanceController::class, 'approve']);
    Route::post('/candidates/{id}/reject', [GovernanceController::class, 'reject']);
    Route::post('/candidates/{id}/revert', [GovernanceController::class, 'revertToPending']);
    Route::post('/candidates/{id}/promote', [GovernanceController::class, 'promote']);
    Route::post('/candidates/{id}/rollback', [GovernanceController::class, 'rollbackCandidate']);
    Route::get('/capabilities', [GovernanceController::class, 'capabilities']);
    Route::post('/capabilities/{id}/rollback', [GovernanceController::class, 'rollbackCapability']);
    Route::get('/evolution-log', [GovernanceController::class, 'evolutionLog']);
});

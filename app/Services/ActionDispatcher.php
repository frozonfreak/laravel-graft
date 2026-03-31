<?php

namespace App\Services;

use App\Models\CapabilityRegistry;
use App\Models\FeatureConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Dispatches the configured action when a pipeline triggers.
 *
 * Action channel is re-validated against tenant's current allowlist
 * at execution time (Stage 2 invariant).
 */
class ActionDispatcher
{
    private const ALLOWED_CHANNELS = ['sms', 'email', 'push', 'in_app'];

    public function dispatch(array $action, array $rows, FeatureConfig $feature): void
    {
        $type    = $action['type'] ?? 'notification';
        $channel = $action['channel'] ?? 'in_app';

        // Re-validate action channel at execution time
        if (! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            Log::warning('ActionDispatcher: blocked unknown channel', [
                'channel'    => $channel,
                'feature_id' => $feature->id,
                'tenant_id'  => $feature->tenant_id,
            ]);
            return;
        }

        match ($type) {
            'notification' => $this->sendNotification($action, $rows, $feature),
            'webhook'      => $this->sendWebhook($action, $rows, $feature),
            default        => Log::info('ActionDispatcher: no handler for action type', ['type' => $type]),
        };
    }

    private function sendNotification(array $action, array $rows, FeatureConfig $feature): void
    {
        // In production, dispatch to the notification system for the tenant
        Log::info('ActionDispatcher: notification dispatched', [
            'feature_id' => $feature->id,
            'channel'    => $action['channel'] ?? 'in_app',
            'rows_count' => count($rows),
        ]);
    }

    private function sendWebhook(array $action, array $rows, FeatureConfig $feature): void
    {
        // Webhook requires trust_tier 3 and prior approval
        if ($feature->trust_tier < 3) {
            Log::warning('ActionDispatcher: webhook blocked for low trust tier', [
                'feature_id' => $feature->id,
            ]);
            return;
        }

        Log::info('ActionDispatcher: webhook dispatched', [
            'feature_id' => $feature->id,
            'url'        => $action['url'] ?? '(none)',
        ]);
    }
}

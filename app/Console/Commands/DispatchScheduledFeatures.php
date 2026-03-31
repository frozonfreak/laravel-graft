<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteFeature;
use App\Models\FeatureConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Runs every minute via the scheduler.
 * Checks all active scheduled features and dispatches ExecuteFeature jobs
 * for those whose cron expression matches the current time.
 */
class DispatchScheduledFeatures extends Command
{
    protected $signature   = 'features:dispatch-scheduled';
    protected $description = 'Dispatch queued jobs for all active scheduled features whose cron is due.';

    public function handle(): int
    {
        $now = now();

        $features = FeatureConfig::query()
            ->where('status', 'active')
            ->whereNotNull('schedule')
            ->with('tenant')
            ->get();

        $dispatched = 0;

        foreach ($features as $feature) {
            if (! $this->isDue($feature->schedule, $now)) {
                continue;
            }

            $idempotencyKey = ExecuteFeature::idempotencyKey(
                $feature->id,
                $now->format('Y-m-d_Hi'),
                $feature->feature_version,
            );

            // Skip if already dispatched for this time window
            $cacheKey = "feature_dispatched:{$idempotencyKey}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            Cache::put($cacheKey, true, now()->addMinutes(10));

            ExecuteFeature::dispatch(
                $feature->id,
                $feature->tenant_id,
                $now->toDateString(),
            );

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} feature execution(s).");

        return self::SUCCESS;
    }

    private function isDue(array $schedule, \Carbon\Carbon $now): bool
    {
        $expression = $schedule['expression'] ?? null;
        if (! $expression) return false;

        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) return false;

        [$minute, $hour, $dom, $month, $dow] = $parts;

        return $this->matchesCronPart($minute, (int) $now->format('i'))
            && $this->matchesCronPart($hour, (int) $now->format('G'))
            && $this->matchesCronPart($dom, (int) $now->format('j'))
            && $this->matchesCronPart($month, (int) $now->format('n'))
            && $this->matchesCronPart($dow, (int) $now->format('w'));
    }

    private function matchesCronPart(string $part, int $value): bool
    {
        if ($part === '*') return true;

        if (str_contains($part, ',')) {
            return in_array($value, array_map('intval', explode(',', $part)), true);
        }

        if (str_contains($part, '-')) {
            [$from, $to] = array_map('intval', explode('-', $part));
            return $value >= $from && $value <= $to;
        }

        if (str_contains($part, '/')) {
            [$range, $step] = explode('/', $part);
            $step = (int) $step;
            return $step > 0 && $value % $step === 0;
        }

        return (int) $part === $value;
    }
}

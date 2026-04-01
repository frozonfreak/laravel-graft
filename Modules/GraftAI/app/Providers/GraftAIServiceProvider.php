<?php

namespace Modules\GraftAI\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\GraftAI\Services\AiSpecGenerator;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GraftAIServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'GraftAI';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'graftai';

    /**
     * Console commands registered by this module.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\GraftAI\Console\Commands\DetectPatterns::class,
        \Modules\GraftAI\Console\Commands\DispatchScheduledFeatures::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Register module-specific service bindings.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(AiSpecGenerator::class, function ($app) {
            return new AiSpecGenerator(
                apiKey: config('services.anthropic.key', ''),
            );
        });
    }

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // Dispatch scheduled feature jobs every minute
        $schedule->command('features:dispatch-scheduled')->everyMinute();

        // Run the pattern detector daily at 02:00 to surface promotion candidates
        $schedule->command('evolution:detect-patterns')->dailyAt('02:00');
    }
}

<?php

namespace GraftAI\Providers;

use GraftAI\Console\Commands\DetectPatterns;
use GraftAI\Console\Commands\DispatchScheduledFeatures;
use GraftAI\Services\AiSpecGenerator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GraftAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/graftai.php', 'graftai');

        $this->app->singleton(AiSpecGenerator::class, function ($app) {
            return new AiSpecGenerator(
                apiKey: config('services.anthropic.key', ''),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'graftai');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([DetectPatterns::class, DispatchScheduledFeatures::class]);
            $this->registerPublishes();
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('features:dispatch-scheduled')->everyMinute();
            $schedule->command('evolution:detect-patterns')->dailyAt('02:00');
        });
    }

    protected function registerRoutes(): void
    {
        if (config('graftai.routes.web', true)) {
            Route::middleware('web')
                ->group(__DIR__.'/../../routes/web.php');
        }

        if (config('graftai.routes.api', true)) {
            Route::middleware('api')
                ->prefix('api')
                ->name('api.')
                ->group(__DIR__.'/../../routes/api.php');
        }
    }

    protected function registerPublishes(): void
    {
        $this->publishes([
            __DIR__.'/../../config/graftai.php' => config_path('graftai.php'),
        ], 'graftai-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'graftai-migrations');

        $this->publishes([
            __DIR__.'/../../database/seeders' => database_path('seeders/GraftAI'),
        ], 'graftai-seeders');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/graftai'),
        ], 'graftai-views');
    }
}

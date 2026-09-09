<?php

namespace Coyote6\LaravelLocale\Providers;

use Coyote6\LaravelLocale\Console\Commands\SyncLocaleData;
use Coyote6\LaravelLocale\LocaleManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class LocaleServiceProvider extends ServiceProvider
{


    // Register
    //
    // Merges this package's config into the application's config
    // repository, and binds the LocaleManager the Locale facade resolves
    // to.
    //
    // @return void
    //
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/locale.php', 'locale');

        $this->app->singleton(LocaleManager::class);
    }


    // Boot
    //
    // Publishes the package config, loads migrations, registers the
    // locale:sync Artisan command, and — unless sync.frequency is
    // 'manual' — schedules it to run automatically at that frequency.
    //
    // @see scheduleSync()
    //
    // @return void
    //
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/locale.php' => config_path('locale.php'),
        ], 'locale-config');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncLocaleData::class,
            ]);
        }

        $this->app->booted(function () {
            $this->scheduleSync();
        });
    }


    // Schedule Sync
    //
    // Registers locale:sync on Laravel's scheduler at whatever frequency
    // config('locale.sync.frequency') names, or registers nothing at all
    // when it's 'manual'.
    //
    // @return void
    //
    protected function scheduleSync(): void
    {
        $frequency = config('locale.sync.frequency');

        if ($frequency === 'manual') {
            return;
        }

        $event = $this->app->make(Schedule::class)->command(SyncLocaleData::class);

        match ($frequency) {
            'daily' => $event->daily(),
            'weekly' => $event->weekly(),
            'yearly' => $event->yearly(),
            default => $event->monthly(),
        };
    }
}

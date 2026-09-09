<?php

namespace Coyote6\LaravelLocale\Tests;

use Coyote6\LaravelLocale\Providers\LocaleServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LocaleServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // No dataset overrides needed here — every dataset already defaults
        // to true in the package's own config, so migrations create every
        // table for the whole suite. Tests that specifically want to prove
        // "table genuinely doesn't exist" behavior drop the relevant
        // table(s) directly (see ModelsTest's disabled-dataset test).
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }
}

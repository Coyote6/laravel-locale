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
        $app['config']->set('database.connections.testing', $this->databaseConnectionConfig());

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


    // Database Connection Config
    //
    // Sqlite in-memory by default (local dev, and the main CI matrix) --
    // or, when DB_CONNECTION is set (the CI real-MariaDB leg), a real
    // connection built from the standard DB_* environment variables.
    //
    // @ai
    //		Deliberately not named test*() -- this project's Pint config
    //		rewrites any TestCase method starting with "test" to snake_case
    //		(php_unit_method_casing, tuned for Pest-style test names), which
    //		would mangle a helper method like this one that just happens to
    //		share that prefix without being an actual test.
    //
    // @ai
    //		'foreign_key_constraints' => true is deliberate, not a default
    //		Laravel picks for you: SQLiteConnector::configureForeignKeyConstraints()
    //		only runs `PRAGMA foreign_keys = ...` when this key is actually
    //		present in the connection array -- leave it unset and SQLite
    //		silently keeps its own native default (OFF), meaning every FK
    //		constraint in this package's migrations would never actually be
    //		enforced in a local/CI-sqlite test run, and a real ordering bug
    //		would pass here and only ever surface against a real MySQL/
    //		MariaDB/Postgres connection (i.e. the test-mariadb CI leg). Set
    //		explicitly so local `vendor/bin/pest` runs mean the same thing
    //		production does.
    //
    // @return array
    //
    protected function databaseConnectionConfig(): array
    {
        if (env('DB_CONNECTION') === null) {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => env('DB_CONNECTION'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'testing'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }
}

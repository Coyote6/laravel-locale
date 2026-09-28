<?php

use Coyote6\LaravelLocale\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class)->in(__DIR__);

// Detach Foreign Key
//
// Drops the foreign key on $table.$column. Several tests simulate "this
// dataset was off when its migration ran" by dropping the *target* table of
// one of this package's conditional foreign keys (see the migrations'
// @ai notes) while a *referencing* table stays and gets a fresh insert --
// e.g. dropping 'countries' while still creating a State/City row. Since
// TestCase::setUp() migrates once with every dataset on, that referencing
// table's foreign_key_constraints already exist from that initial migrate
// and don't vanish just because the target table (or config) changes later
// -- this has to be called first, or the insert throws
// "no such table" (sqlite) / a real FK error (MySQL/MariaDB/Postgres) on
// the now-dangling constraint. The real, non-test-only version of exactly
// this (detach before drop, driven by what's actually in the database) is
// locale:config-refresh's own job.
//
// @param $table string - The table the foreign key is defined on [Ex: 'states']
// @param $column string - The column the foreign key is on [Ex: 'country_id']
//
// @return void
//
function detachForeignKey(string $table, string $column): void
{
    Schema::table($table, function (Blueprint $blueprint) use ($column): void {
        $blueprint->dropForeign([$column]);
    });
}

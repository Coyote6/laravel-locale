<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the country_timezone pivot table. Named to match Laravel's
    // own pivot-naming convention (singular model names, alphabetical,
    // underscore-joined) by default — see config/locale.php's
    // 'table_names' block to rename it. Skipped entirely when
    // config('locale.datasets.timezones') is off.
    //
    // @ai
    //		country_id carries no hard FK constraint — countries is its own
    //		toggleable dataset, independent of timezones, so this table may
    //		exist while countries doesn't. timezone_id keeps its constraint:
    //		this table and 'timezones' are both gated by the same
    //		config('locale.datasets.timezones') flag, so if this table
    //		exists, timezones always does too.
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.timezones')) {
            return;
        }

        Schema::create(config('locale.table_names.country_timezone'), function (Blueprint $table) {
            $table->char('country_id', 2);
            $table->string('timezone_id');

            $table->primary(['country_id', 'timezone_id']);

            $table->foreign('timezone_id')
                ->references('id')
                ->on(config('locale.table_names.timezones'))
                ->cascadeOnDelete();
        });
    }


    // Down
    //
    // @return void
    //
    public function down(): void
    {
        Schema::dropIfExists(config('locale.table_names.country_timezone'));
    }
};

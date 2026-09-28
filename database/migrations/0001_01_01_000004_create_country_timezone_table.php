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
    //		timezone_id's constraint is unconditional: this table and
    //		'timezones' are both gated by the same
    //		config('locale.datasets.timezones') flag, so if this table
    //		exists, timezones always does too. country_id's constraint is
    //		conditional -- countries is independently toggleable, so this
    //		table may exist while countries doesn't -- added only when
    //		config('locale.datasets.countries') is ALSO on at the moment
    //		this migration runs. cascadeOnDelete() on both: this column is
    //		required (never null), and a pivot row with no country/timezone
    //		to pivot doesn't make sense to keep. locale:config-refresh is
    //		what keeps the country_id FK in sync when countries is toggled
    //		after this migration already ran -- see its own docblock for
    //		why that can't be inferred from config alone.
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

            if (config('locale.datasets.countries')) {
                $table->foreign('country_id')
                    ->references('id')
                    ->on(config('locale.table_names.countries'))
                    ->cascadeOnDelete();
            }
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

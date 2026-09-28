<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the cities table, keyed by the source's own stable numeric
    // id (unlike every other table here, cities have no ISO-style code to
    // key on instead). Skipped entirely when config('locale.datasets.cities')
    // is off.
    //
    // @ai
    //		country_id/state_id/timezone_id each get a real, conditional
    //		foreign key — countries, states, and timezones are each
    //		independently toggleable datasets, so a city's country/state/
    //		timezone table may not exist even while cities does; each FK is
    //		added only when its own target dataset is ALSO on at the moment
    //		this migration runs. state_id/timezone_id are only populated
    //		when their own dataset is also enabled, left null otherwise —
    //		City::state()/timezone() rely on that null to stay safe via
    //		Eloquent's own null-foreign-key short-circuit regardless of
    //		whether the FK constraint itself is present; nullOnDelete()
    //		matches. country_id is different: it's always populated
    //		regardless of config('locale.datasets.countries'), since the
    //		country code is already known directly from the source's own
    //		per-city record, no lookup against a countries table required —
    //		see Models\City::country() for how the relationship stays safe
    //		even when the FK isn't present. cascadeOnDelete() since this
    //		column is required (never null). locale:config-refresh is what
    //		keeps these in sync when a dataset is toggled after this
    //		migration already ran — see its own docblock for why that
    //		can't be inferred from config alone.
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.cities')) {
            return;
        }

        Schema::create(config('locale.table_names.cities'), function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->char('country_id', 2);
            $table->string('state_id')->nullable();
            $table->string('timezone_id')->nullable();
            $table->string('name');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('state_id');
            $table->index('name');

            if (config('locale.datasets.countries')) {
                $table->foreign('country_id')
                    ->references('id')
                    ->on(config('locale.table_names.countries'))
                    ->cascadeOnDelete();
            }

            if (config('locale.datasets.states')) {
                $table->foreign('state_id')
                    ->references('id')
                    ->on(config('locale.table_names.states'))
                    ->nullOnDelete();
            }

            if (config('locale.datasets.timezones')) {
                $table->foreign('timezone_id')
                    ->references('id')
                    ->on(config('locale.table_names.timezones'))
                    ->nullOnDelete();
            }
        });
    }


    // Down
    //
    // @return void
    //
    public function down(): void
    {
        Schema::dropIfExists(config('locale.table_names.cities'));
    }
};

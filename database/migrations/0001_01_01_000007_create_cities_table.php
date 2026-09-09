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
    //		country_id/state_id/timezone_id all carry no hard FK constraint
    //		— countries, states, and timezones are each independently
    //		toggleable datasets, so a city's country/state/timezone table
    //		may not exist even while cities does. state_id/timezone_id are
    //		only populated when their own dataset is also enabled, left
    //		null otherwise — City::state()/timezone() rely on that null to
    //		stay safe via Eloquent's own null-foreign-key short-circuit.
    //		country_id is different: it's always populated regardless of
    //		config('locale.datasets.countries'), since the country code is
    //		already known directly from the source's own per-city record,
    //		no lookup against a countries table required — see
    //		Models\City::country() for how a non-null FK to a possibly
    //		nonexistent table stays safe anyway.
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

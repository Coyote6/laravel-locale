<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the countries table, keyed by ISO 3166-1 alpha-2 code. Skipped
    // entirely when config('locale.datasets.countries') is off — see
    // Models\State::country() and Models\City::country() for how that stays
    // safe to call anyway.
    //
    // @ai
    //		currency_id has no hard foreign key constraint, unlike an earlier
    //		version of this migration — currencies is now a toggleable
    //		dataset like states/timezones/regions/subregions, so its table
    //		may not exist at all. A constraint referencing a table that
    //		might not exist would break `migrate` outright. GeoDataImporter
    //		only ever writes a real value into this column when
    //		config('locale.datasets.currencies') is on (see upsertCountry()),
    //		leaving it null otherwise — the same null-FK safety net
    //		Country::currency() already relies on.
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.countries')) {
            return;
        }

        Schema::create(config('locale.table_names.countries'), function (Blueprint $table) {
            $table->char('id', 2)->primary();
            $table->char('iso3', 3)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->char('numeric_code', 3)->nullable();
            $table->string('phone_code')->nullable();
            $table->string('capital')->nullable();
            $table->char('currency_id', 3)->nullable();
            $table->string('tld')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->string('region_id')->nullable();
            $table->string('subregion_id')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('emoji')->nullable();
            $table->string('emoji_unicode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }


    // Down
    //
    // @return void
    //
    public function down(): void
    {
        Schema::dropIfExists(config('locale.table_names.countries'));
    }
};

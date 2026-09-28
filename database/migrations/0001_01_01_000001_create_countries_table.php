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
    //		currency_id/region_id/subregion_id each get a real, conditional
    //		foreign key -- added only when their target dataset is ALSO
    //		enabled at the moment this migration runs (config('locale.datasets.currencies')/
    //		'regions'/'subregions'), so the constraint is never added
    //		against a table that might not exist. GeoDataImporter only ever
    //		writes a real value into these columns when the matching
    //		dataset is on (see upsertCountry()), leaving them null
    //		otherwise, so nullOnDelete() is the safe default either way.
    //		locale:config-refresh is what keeps these in sync when a
    //		dataset is toggled after this migration already ran -- see its
    //		own docblock for why that can't be inferred from config alone.
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

            if (config('locale.datasets.currencies')) {
                $table->foreign('currency_id')
                    ->references('id')
                    ->on(config('locale.table_names.currencies'))
                    ->nullOnDelete();
            }

            if (config('locale.datasets.regions')) {
                $table->foreign('region_id')
                    ->references('id')
                    ->on(config('locale.table_names.regions'))
                    ->nullOnDelete();
            }

            if (config('locale.datasets.subregions')) {
                $table->foreign('subregion_id')
                    ->references('id')
                    ->on(config('locale.table_names.subregions'))
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
        Schema::dropIfExists(config('locale.table_names.countries'));
    }
};

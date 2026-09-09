<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the states table, keyed by ISO 3166-2 code (e.g. "US-CA").
    // Skipped entirely when config('locale.datasets.states') is off — see
    // Models\Country::states() for how that stays safe to call anyway.
    //
    // @ai
    //		country_id carries no hard foreign key constraint — countries is
    //		now its own toggleable dataset (see config/locale.php), so its
    //		table may not exist at all even while states does. Unlike
    //		region_id/currency_id elsewhere in this package, this column is
    //		still always populated regardless: the country code is already
    //		known directly from the source's own per-state record, no
    //		lookup against a countries table required. See
    //		GeoDataImporter::upsertState() and Models\State::country().
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.states')) {
            return;
        }

        Schema::create(config('locale.table_names.states'), function (Blueprint $table) {
            $table->string('id')->primary();
            $table->char('country_id', 2);
            $table->string('code');
            $table->string('name');
            $table->string('type')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
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
        Schema::dropIfExists(config('locale.table_names.states'));
    }
};

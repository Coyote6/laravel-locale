<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the currencies table, keyed by ISO 4217 code (e.g. "USD").
    // Skipped entirely when config('locale.datasets.currencies') is off, the
    // same as every other optional dataset in this package — countries.currency_id
    // has no hard foreign key constraint for exactly this reason (see the
    // countries migration).
    //
    // @ai
    //		Deduplicated the same way Timezone is: many countries share one
    //		currency (every Eurozone country uses EUR), so this is a real
    //		normalized table with a countries.currency_id belongsTo, not
    //		columns repeated on every country row. See
    //		GeoDataImporter::upsertCurrency().
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.currencies')) {
            return;
        }

        Schema::create(config('locale.table_names.currencies'), function (Blueprint $table) {
            $table->char('id', 3)->primary();
            $table->string('name');
            $table->string('symbol')->nullable();
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
        Schema::dropIfExists(config('locale.table_names.currencies'));
    }
};

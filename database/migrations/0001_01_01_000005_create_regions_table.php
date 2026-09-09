<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the regions table, keyed by a slug of the region's name (no
    // ISO-style code exists for continents/regions). Skipped entirely when
    // config('locale.datasets.regions') is off — see
    // Models\Region::subregions() for how that stays safe to call anyway.
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.regions')) {
            return;
        }

        Schema::create(config('locale.table_names.regions'), function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->json('translations')->nullable();
            $table->string('wikidata_id')->nullable();
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
        Schema::dropIfExists(config('locale.table_names.regions'));
    }
};

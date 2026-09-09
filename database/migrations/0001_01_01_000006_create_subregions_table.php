<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the subregions table, keyed by a slug of the subregion's
    // name. Skipped entirely when config('locale.datasets.subregions') is
    // off.
    //
    // @ai
    //		region_id carries no hard FK constraint — regions and
    //		subregions are independently toggleable datasets (both default
    //		on, but either can be turned off separately), so a subregion's
    //		region_id may be populated (or this table may exist at all)
    //		even when the regions table doesn't. Same reasoning as
    //		countries' region_id/subregion_id.
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.subregions')) {
            return;
        }

        Schema::create(config('locale.table_names.subregions'), function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('region_id')->nullable();
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
        Schema::dropIfExists(config('locale.table_names.subregions'));
    }
};

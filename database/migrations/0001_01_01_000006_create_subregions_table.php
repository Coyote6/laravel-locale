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
    //		region_id gets a real, conditional foreign key -- regions and
    //		subregions are independently toggleable datasets (both default
    //		on, but either can be turned off separately), so it's added
    //		only when config('locale.datasets.regions') is ALSO on at the
    //		moment this migration runs. Same reasoning as countries'
    //		region_id/subregion_id. nullOnDelete() since this column is
    //		nullable. locale:config-refresh is what keeps this FK in sync
    //		when regions is toggled after this migration already ran --
    //		see its own docblock for why that can't be inferred from
    //		config alone.
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

            if (config('locale.datasets.regions')) {
                $table->foreign('region_id')
                    ->references('id')
                    ->on(config('locale.table_names.regions'))
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
        Schema::dropIfExists(config('locale.table_names.subregions'));
    }
};

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
    //		country_id gets a real, conditional foreign key -- added only
    //		when config('locale.datasets.countries') is ALSO on at the
    //		moment this migration runs, so the constraint is never added
    //		against a table that might not exist. Unlike region_id/
    //		currency_id elsewhere in this package, this column is always
    //		populated regardless of whether the FK gets added: the country
    //		code is already known directly from the source's own per-state
    //		record, no lookup against a countries table required. See
    //		GeoDataImporter::upsertState() and Models\State::country() for
    //		how the relationship itself stays safe even when the FK isn't
    //		present. cascadeOnDelete() since this column is required
    //		(never null) -- a state with no country doesn't make sense to
    //		keep. locale:config-refresh is what keeps this FK in sync when
    //		a dataset is toggled after this migration already ran -- see
    //		its own docblock for why that can't be inferred from config
    //		alone.
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
        Schema::dropIfExists(config('locale.table_names.states'));
    }
};

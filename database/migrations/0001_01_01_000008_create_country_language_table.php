<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the country_language pivot table. Named to match Laravel's
    // own pivot-naming convention (singular model names, alphabetical,
    // underscore-joined) by default — see config/locale.php's
    // 'table_names' block to rename it. Skipped entirely when
    // config('locale.datasets.country_languages') is off — see
    // Models\Country::languages() for how that stays safe to call anyway.
    //
    // @ai
    //		language_code never gets a FK — there is no languages table
    //		(see src/LocaleManager.php; language data is looked up live via
    //		Symfony Intl, never stored). This table only stores which
    //		language codes a country is linked to. country_id gets a real,
    //		conditional foreign key — countries is its own toggleable
    //		dataset, so it's added only when config('locale.datasets.countries')
    //		is ALSO on at the moment this migration runs — see
    //		GeoDataImporter::importCountryLanguages() for how it handles
    //		writing this table when the countries table it would otherwise
    //		validate against doesn't exist. cascadeOnDelete() since this
    //		column is required (never null). locale:config-refresh is what
    //		keeps this FK in sync when countries is toggled after this
    //		migration already ran — see its own docblock for why that
    //		can't be inferred from config alone.
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.country_languages')) {
            return;
        }

        Schema::create(config('locale.table_names.country_language'), function (Blueprint $table) {
            $table->char('country_id', 2);
            $table->string('language_code');

            $table->primary(['country_id', 'language_code']);

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
        Schema::dropIfExists(config('locale.table_names.country_language'));
    }
};

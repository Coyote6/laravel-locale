<?php

namespace Coyote6\LaravelLocale\Console\Commands;

use Coyote6\LaravelLocale\Support\GeoDataImporter;
use Illuminate\Console\Command;

class SyncLocaleData extends Command
{


    // Command
    protected $signature = 'locale:sync';

    protected $description = 'Import countries, states, timezones, and (if enabled) cities/regions/subregions/country-language links from dr5hn/countries-states-cities-database and mledoze/countries';


    // Handle
    //
    // Runs regions/subregions first (if enabled), then the geo-data import
    // (always), then country-language links if enabled, reports any
    // timezone/language codes the source provided that this PHP
    // installation/Symfony Intl doesn't recognize (skipped, not imported —
    // see GeoDataImporter), and reports how many cities were imported when
    // that dataset is enabled.
    //
    // @ai
    //		Regions/subregions must run BEFORE importGeoData() -- Country
    //		rows written there get region_id/subregion_id slugs whenever
    //		those datasets are on (see GeoDataImporter::upsertCountry()),
    //		and since countries.region_id/subregion_id now carry a real
    //		conditional foreign key (added whenever regions/subregions is
    //		on), inserting a country before the region/subregion row it
    //		points at exists would violate that constraint on any driver
    //		that enforces it. This was silently fine before those
    //		constraints existed; it's a hard ordering requirement now.
    //
    // @param $importer \Coyote6\LaravelLocale\Support\GeoDataImporter
    //
    // @return int
    //
    public function handle(GeoDataImporter $importer): int
    {
        if (config('locale.datasets.regions') || config('locale.datasets.subregions')) {
            $this->components->task('Importing regions and subregions', function () use ($importer) {
                $importer->importRegionsAndSubregions();
            });
        }

        $label = config('locale.datasets.cities')
            ? 'Importing countries, states, timezones, and cities (this can take a while)'
            : 'Importing countries, states, and timezones';

        $this->components->task($label, function () use ($importer) {
            $importer->importGeoData();
        });

        if (config('locale.datasets.country_languages')) {
            $this->components->task('Importing country-language links', function () use ($importer) {
                $importer->importCountryLanguages();
            });
        }

        foreach ($importer->invalidZones() as $zone) {
            $this->components->warn("Skipped timezone \"{$zone}\" — not recognized by this PHP installation's tzdata.");
        }

        foreach ($importer->invalidLanguageCodes() as $code) {
            $this->components->warn("Skipped language code \"{$code}\" — not recognized by Symfony Intl.");
        }

        if (config('locale.datasets.cities')) {
            $this->components->info("Imported {$importer->citiesImported()} cities.");
        }

        $this->components->info('Locale data sync complete.');

        return self::SUCCESS;
    }
}

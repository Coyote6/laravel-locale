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
    // Runs the geo-data import (always), then regions/subregions and
    // country-language links if enabled, reports any timezone/language
    // codes the source provided that this PHP installation/Symfony Intl
    // doesn't recognize (skipped, not imported — see GeoDataImporter), and
    // reports how many cities were imported when that dataset is enabled.
    //
    // @param $importer \Coyote6\LaravelLocale\Support\GeoDataImporter
    //
    // @return int
    //
    public function handle(GeoDataImporter $importer): int
    {
        $label = config('locale.datasets.cities')
            ? 'Importing countries, states, timezones, and cities (this can take a while)'
            : 'Importing countries, states, and timezones';

        $this->components->task($label, function () use ($importer) {
            $importer->importGeoData();
        });

        if (config('locale.datasets.regions') || config('locale.datasets.subregions')) {
            $this->components->task('Importing regions and subregions', function () use ($importer) {
                $importer->importRegionsAndSubregions();
            });
        }

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

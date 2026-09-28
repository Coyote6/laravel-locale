<?php

use Coyote6\LaravelLocale\Models\City;
use Coyote6\LaravelLocale\Models\Country;
use Coyote6\LaravelLocale\Models\Currency;
use Coyote6\LaravelLocale\Models\State;
use Coyote6\LaravelLocale\Models\Timezone;
use Coyote6\LaravelLocale\Support\GeoDataImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function fakeGeoRelease(): array
{
    return [
        [
            'id' => 1,
            'name' => 'United States',
            'iso3' => 'USA',
            'iso2' => 'US',
            'numeric_code' => '840',
            'phonecode' => '1',
            'capital' => 'Washington',
            'currency' => 'USD',
            'currency_name' => 'US Dollar',
            'currency_symbol' => '$',
            'tld' => '.us',
            'native' => 'United States',
            'region' => 'Americas',
            'region_id' => 2,
            'subregion' => 'Northern America',
            'subregion_id' => 9,
            'latitude' => '38.00000000',
            'longitude' => '-97.00000000',
            'emoji' => '🇺🇸',
            'emojiU' => 'U+1F1FA U+1F1F8',
            'states' => [
                [
                    'id' => 100,
                    'name' => 'California',
                    'iso2' => 'CA',
                    'iso3166_2' => 'US-CA',
                    'native' => null,
                    'latitude' => '36.7',
                    'longitude' => '-119.4',
                    'type' => 'state',
                    'timezone' => 'America/Los_Angeles',
                    'cities' => [
                        [
                            'id' => 5368361,
                            'name' => 'Los Angeles',
                            'latitude' => '34.05223000',
                            'longitude' => '-118.24368000',
                            'timezone' => 'America/Los_Angeles',
                        ],
                        [
                            'id' => 5391811,
                            'name' => 'San Francisco',
                            'latitude' => '37.77493000',
                            'longitude' => '-122.41942000',
                            // A real deprecated IANA alias — should be skipped, not imported.
                            'timezone' => 'Europe/Kiev',
                        ],
                    ],
                ],
            ],
            'timezones' => [
                [
                    'zoneName' => 'America/Chicago',
                    'gmtOffset' => -21600,
                    'gmtOffsetName' => 'UTC-06:00',
                    'abbreviation' => 'CST',
                    'tzName' => 'Central Standard Time',
                ],
                [
                    'zoneName' => 'America/Los_Angeles',
                    'gmtOffset' => -28800,
                    'gmtOffsetName' => 'UTC-08:00',
                    'abbreviation' => 'PST',
                    'tzName' => 'Pacific Standard Time',
                ],
                // A real deprecated IANA alias — should be skipped, not imported.
                [
                    'zoneName' => 'Europe/Kiev',
                    'gmtOffset' => 7200,
                    'gmtOffsetName' => 'UTC+02:00',
                    'abbreviation' => 'EET',
                    'tzName' => 'Eastern European Time',
                ],
            ],
        ],
    ];
}

test('imports countries, states, and timezones, skipping zone names PHP does not recognize', function () {
    // This test doesn't exercise region/subregion linkage, and fakeGeoRelease()
    // includes region/subregion fields -- upsertCountry() would otherwise try
    // to set region_id/subregion_id to slugs that don't exist as real rows
    // (importRegionsAndSubregions() is a separate call this test never
    // makes), violating countries' conditional FK to those tables.
    config(['locale.datasets.regions' => false, 'locale.datasets.subregions' => false]);

    Http::fake([
        '*' => Http::response(gzencode(json_encode(fakeGeoRelease()))),
    ]);

    $importer = new GeoDataImporter;
    $importer->importGeoData();

    $country = Country::query()->find('US');
    expect($country)->not->toBeNull()
        ->and($country->iso3)->toBe('USA')
        ->and($country->currency_id)->toBe('USD')
        ->and($country->currency->name)->toBe('US Dollar')
        ->and($country->currency->symbol)->toBe('$');

    $state = State::query()->find('US-CA');
    expect($state)->not->toBeNull()
        ->and($state->country_id)->toBe('US')
        ->and($state->code)->toBe('CA');

    expect(Timezone::query()->find('America/Chicago'))->not->toBeNull()
        ->and(Timezone::query()->find('Europe/Kiev'))->toBeNull();

    expect($country->timezones()->pluck('id')->sort()->values()->all())
        ->toBe(['America/Chicago', 'America/Los_Angeles']);

    expect($importer->invalidZones())->toBe(['Europe/Kiev']);
});

test('imports cities keyed by the source\'s own id, resolving valid timezones and skipping invalid ones', function () {
    // See the first test's comment above -- same reasoning.
    config(['locale.datasets.regions' => false, 'locale.datasets.subregions' => false]);

    Http::fake([
        '*' => Http::response(gzencode(json_encode(fakeGeoRelease()))),
    ]);

    $importer = new GeoDataImporter;
    $importer->importGeoData();

    expect($importer->citiesImported())->toBe(2);

    $la = City::query()->find(5368361);
    expect($la)->not->toBeNull()
        ->and($la->name)->toBe('Los Angeles')
        ->and($la->country_id)->toBe('US')
        ->and($la->state_id)->toBe('US-CA')
        ->and($la->timezone_id)->toBe('America/Los_Angeles');

    $sf = City::query()->find(5391811);
    expect($sf)->not->toBeNull()
        ->and($sf->timezone_id)->toBeNull(); // source gave an invalid zone name for this one

    expect($la->country->id)->toBe('US')
        ->and($la->state->id)->toBe('US-CA')
        ->and($la->timezone->id)->toBe('America/Los_Angeles');
});

test('re-running the import upserts rather than duplicating, including cities', function () {
    // See the first test's comment above -- same reasoning.
    config(['locale.datasets.regions' => false, 'locale.datasets.subregions' => false]);

    Http::fake([
        '*' => Http::response(gzencode(json_encode(fakeGeoRelease()))),
    ]);

    $importer = new GeoDataImporter;
    $importer->importGeoData();
    $importer->importGeoData();

    expect(Country::query()->count())->toBe(1)
        ->and(State::query()->count())->toBe(1)
        ->and(Timezone::query()->count())->toBe(2)
        ->and(City::query()->count())->toBe(2);
});

test('shared currencies are deduplicated into one row, not repeated per country', function () {
    $countries = [
        ['id' => 1, 'name' => 'France', 'iso3' => 'FRA', 'iso2' => 'FR', 'currency' => 'EUR', 'currency_name' => 'Euro', 'currency_symbol' => '€'],
        ['id' => 2, 'name' => 'Germany', 'iso3' => 'DEU', 'iso2' => 'DE', 'currency' => 'EUR', 'currency_name' => 'Euro', 'currency_symbol' => '€'],
    ];

    Http::fake([
        '*' => Http::response(gzencode(json_encode($countries))),
    ]);

    (new GeoDataImporter)->importGeoData();

    expect(Currency::query()->count())->toBe(1);

    $eur = Currency::query()->find('EUR');
    expect($eur->name)->toBe('Euro')
        ->and($eur->countries()->pluck('id')->sort()->values()->all())->toBe(['DE', 'FR']);

    expect(Country::query()->find('FR')->currency->id)->toBe('EUR');
});

test('countries dataset off: country_id is still populated everywhere, but no Country row is written', function () {
    // states/cities/country_timezone's country_id FKs (added at the initial
    // full migrate in TestCase::setUp(), when countries was still on) would
    // otherwise dangle once countries is dropped below -- see tests/Pest.php's
    // detachForeignKey().
    detachForeignKey(config('locale.table_names.states'), 'country_id');
    detachForeignKey(config('locale.table_names.cities'), 'country_id');
    detachForeignKey(config('locale.table_names.country_timezone'), 'country_id');

    Schema::dropIfExists(config('locale.table_names.countries'));
    config(['locale.datasets.countries' => false]);

    Http::fake([
        '*' => Http::response(gzencode(json_encode(fakeGeoRelease()))),
    ]);

    $importer = new GeoDataImporter;
    $importer->importGeoData();

    expect(Schema::hasTable(config('locale.table_names.countries')))->toBeFalse();

    $state = State::query()->find('US-CA');
    expect($state)->not->toBeNull()
        ->and($state->country_id)->toBe('US');

    $city = City::query()->find(5368361);
    expect($city)->not->toBeNull()
        ->and($city->country_id)->toBe('US');

    expect(DB::table(config('locale.table_names.country_timezone'))->where('country_id', 'US')->count())->toBe(2);

    // currencies is an independent toggle — still populated even though
    // there's no Country row for it to attach to.
    $usd = Currency::query()->find('USD');
    expect($usd)->not->toBeNull()
        ->and($usd->name)->toBe('US Dollar');
});

<?php

use Coyote6\LaravelLocale\Models\Country;
use Coyote6\LaravelLocale\Support\GeoDataImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function fakeLanguagesSource(): array
{
    return [
        [
            // US exists in the countries table (seeded below) — should import.
            'cca2' => 'US',
            'languages' => [
                'eng' => 'English', // resolves via alpha-2 fallback (en)
                'cnr' => 'Montenegrin', // does not resolve in any form — skipped
            ],
        ],
        [
            // Not in the countries table — should be skipped entirely, not
            // violate country_language's FK constraint on country_id.
            'cca2' => 'XK',
            'languages' => ['sqi' => 'Albanian'],
        ],
    ];
}

test('imports country-language links, resolving alpha-3 codes and skipping unresolvable ones', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    Http::fake([
        '*' => Http::response(json_encode(fakeLanguagesSource())),
    ]);

    $importer = new GeoDataImporter;
    $importer->importCountryLanguages();

    $languages = Country::query()->find('US')->languages();

    expect($languages)->toHaveCount(1)
        ->and($languages[0]->id)->toBe('en')
        ->and($languages[0]->name)->toBe('English');

    expect($importer->invalidLanguageCodes())->toBe(['cnr']);

    // XK was never a valid country, so nothing should exist for it at all.
    expect(DB::table(config('locale.table_names.country_language'))->where('country_id', 'XK')->exists())->toBeFalse();
});

test('Country::languages returns an empty array when nothing is linked', function () {
    Country::query()->create(['id' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada']);

    expect(Country::query()->find('CA')->languages())->toBe([]);
});

test('imports every code as-is, with no validation, when the countries dataset is off', function () {
    // country_language's country_id FK (added at the initial full migrate
    // in TestCase::setUp(), when countries was still on) would otherwise
    // dangle once countries is dropped below -- see tests/Pest.php's
    // detachForeignKey().
    detachForeignKey(config('locale.table_names.country_language'), 'country_id');

    Schema::dropIfExists(config('locale.table_names.countries'));
    config(['locale.datasets.countries' => false]);

    Http::fake([
        '*' => Http::response(json_encode(fakeLanguagesSource())),
    ]);

    $importer = new GeoDataImporter;
    $importer->importCountryLanguages();

    // No countries table to validate against — both codes get written,
    // including 'XK' (which the other test proves gets filtered out when
    // countries IS enabled).
    expect(DB::table(config('locale.table_names.country_language'))->where('country_id', 'US')->exists())->toBeTrue()
        ->and(DB::table(config('locale.table_names.country_language'))->where('country_id', 'XK')->exists())->toBeTrue();
});

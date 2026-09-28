<?php

use Coyote6\LaravelLocale\Models\City;
use Coyote6\LaravelLocale\Models\Country;
use Coyote6\LaravelLocale\Models\Currency;
use Coyote6\LaravelLocale\Models\Region;
use Coyote6\LaravelLocale\Models\State;
use Coyote6\LaravelLocale\Models\Timezone;
use Illuminate\Support\Facades\Schema;

function resolveEmptyRelationTable(object $model): string
{
    return (new ReflectionMethod($model, 'resolveEmptyRelationTable'))->invoke($model);
}

test('resolves the configured empty_relation_table by default', function () {
    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect(resolveEmptyRelationTable($country))->toBe('states');
});

test('falls back when the configured table is the caller\'s own table', function () {
    config(['locale.empty_relation_table' => 'countries']);

    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    // 'countries' would self-join Country against itself — resolution must
    // skip straight to the next candidate in EMPTY_RELATION_DATASETS
    // (currencies), never returning 'countries' itself.
    expect(resolveEmptyRelationTable($country))->toBe('currencies');

    // A model whose own table ISN'T 'countries' can use it as configured,
    // proving the skip above is specific to the collision, not 'countries'
    // being unusable in general.
    $region = Region::query()->create(['id' => 'americas', 'name' => 'Americas']);
    expect(resolveEmptyRelationTable($region))->toBe('countries');
});

test('falls back when the configured table\'s own dataset is disabled', function () {
    // The default ('states') is a real toggleable dataset, not just an
    // arbitrary always-on table — if it's off (table genuinely doesn't
    // exist, simulated by dropping it), resolution must not blindly trust
    // the config value just because it doesn't collide with the caller.
    Schema::dropIfExists(config('locale.table_names.states'));
    config(['locale.datasets.states' => false]);

    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect(resolveEmptyRelationTable($country))->toBe('currencies');
});

test('falls all the way back to migrations when every candidate is disabled or self-joins', function () {
    // countries.currency_id/region_id/subregion_id's FKs (added at the
    // initial full migrate in TestCase::setUp(), when their targets were
    // still on) would otherwise dangle once currencies/regions/subregions
    // are dropped below and a new Country row is created further down --
    // see tests/Pest.php's detachForeignKey().
    detachForeignKey(config('locale.table_names.countries'), 'currency_id');
    detachForeignKey(config('locale.table_names.countries'), 'region_id');
    detachForeignKey(config('locale.table_names.countries'), 'subregion_id');

    foreach (['currencies', 'states', 'timezones', 'regions', 'subregions', 'cities'] as $dataset) {
        Schema::dropIfExists(config("locale.table_names.{$dataset}"));
        config(["locale.datasets.{$dataset}" => false]);
    }

    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect(resolveEmptyRelationTable($country))->toBe('migrations');
});

test('an unrecognized configured table (not one of this package\'s own datasets) is trusted as-is', function () {
    config(['locale.empty_relation_table' => 'migrations']);

    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect(resolveEmptyRelationTable($country))->toBe('migrations');
});

test('Country::timezones() stays safe end-to-end when its resolved fallback table would otherwise self-join', function () {
    config(['locale.empty_relation_table' => 'countries', 'locale.datasets.timezones' => false]);
    Schema::dropIfExists(config('locale.table_names.country_timezone'));
    Schema::dropIfExists(config('locale.table_names.timezones'));

    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect($country->timezones()->get())->toHaveCount(0);
});

test('belongsTo relationships pointing at a disabled countries table stay safe despite a real (non-null) country_id', function () {
    // The scenario emptyBelongsTo() exists for specifically: country_id is
    // NOT null here (unlike the belongsTo-safety-for-free cases elsewhere
    // in this package) — GeoDataImporter keeps writing the real code
    // regardless of config('locale.datasets.countries'), so these can't
    // rely on Eloquent's null-FK short-circuit at all.
    //
    // states.country_id/cities.country_id's FKs (added at the initial full
    // migrate in TestCase::setUp(), when countries was still on) would
    // otherwise dangle once countries is dropped below and new State/City
    // rows are created further down -- see tests/Pest.php's detachForeignKey().
    detachForeignKey(config('locale.table_names.states'), 'country_id');
    detachForeignKey(config('locale.table_names.cities'), 'country_id');

    Schema::dropIfExists(config('locale.table_names.countries'));
    config(['locale.datasets.countries' => false]);

    $state = State::query()->create(['id' => 'US-CA', 'country_id' => 'US', 'code' => 'CA', 'name' => 'California']);
    $city = City::query()->create(['id' => 1, 'country_id' => 'US', 'name' => 'Los Angeles']);
    $timezone = Timezone::query()->create(['id' => 'America/Chicago', 'name' => 'Central Standard Time']);
    $currency = Currency::query()->create(['id' => 'USD', 'name' => 'US Dollar']);

    expect($state->country_id)->toBe('US')
        ->and($state->country()->get())->toHaveCount(0)
        ->and($state->country)->toBeNull()
        ->and($city->country_id)->toBe('US')
        ->and($city->country)->toBeNull()
        ->and($timezone->countries()->get())->toHaveCount(0)
        ->and($timezone->countries)->toHaveCount(0)
        ->and($currency->countries()->get())->toHaveCount(0)
        ->and($currency->countries)->toHaveCount(0);

    // whereHas()/whereDoesntHave() build their own subqueries independently
    // of a plain ->get()/property access — must stay safe too.
    expect(State::whereHas('country')->count())->toBe(0)
        ->and(State::whereDoesntHave('country')->count())->toBe(1)
        ->and(City::whereHas('country')->count())->toBe(0)
        ->and(City::whereDoesntHave('country')->count())->toBe(1);

    // Eager loading must stay safe too, not just the lazy/dynamic paths above.
    $reloadedState = State::with('country')->find('US-CA');
    expect($reloadedState->country)->toBeNull();
});

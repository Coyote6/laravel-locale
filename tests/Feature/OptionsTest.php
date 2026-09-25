<?php

use Coyote6\LaravelLocale\Models\City;
use Coyote6\LaravelLocale\Models\Country;
use Coyote6\LaravelLocale\Models\Region;
use Coyote6\LaravelLocale\Models\State;
use Coyote6\LaravelLocale\Models\Subregion;
use Coyote6\LaravelLocale\Models\Timezone;
use Illuminate\Support\Facades\Schema;

function seedTwoCountriesWithStatesAndCities(): void
{
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);
    Country::query()->create(['id' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada']);

    State::query()->create(['id' => 'US-CA', 'country_id' => 'US', 'code' => 'CA', 'name' => 'California']);
    State::query()->create(['id' => 'US-TX', 'country_id' => 'US', 'code' => 'TX', 'name' => 'Texas']);
    State::query()->create(['id' => 'CA-ON', 'country_id' => 'CA', 'code' => 'ON', 'name' => 'Ontario']);

    City::query()->create(['id' => 1, 'country_id' => 'US', 'state_id' => 'US-CA', 'name' => 'Los Angeles']);
    City::query()->create(['id' => 2, 'country_id' => 'US', 'state_id' => 'US-CA', 'name' => 'San Francisco']);
    City::query()->create(['id' => 3, 'country_id' => 'US', 'state_id' => 'US-TX', 'name' => 'Austin']);
    City::query()->create(['id' => 4, 'country_id' => 'CA', 'state_id' => 'CA-ON', 'name' => 'Toronto']);
}

test('getAsOptions returns id => name ordered by name ascending', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);
    Country::query()->create(['id' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada']);

    expect(Country::getAsOptions())->toBe([
        'CA' => 'Canada',
        'US' => 'United States',
    ]);
});

test('getAsOptions keys and labels by any real column, not just id/name', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);
    Country::query()->create(['id' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada']);

    expect(Country::getAsOptions('iso3'))->toBe(['CAN' => 'Canada', 'USA' => 'United States'])
        ->and(Country::getAsOptions('id', 'iso3'))->toBe(['CA' => 'CAN', 'US' => 'USA']);
});

test('getAsOptions limit and page paginate the result', function () {
    Country::query()->create(['id' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada']);
    Country::query()->create(['id' => 'FR', 'iso3' => 'FRA', 'name' => 'France']);
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect(Country::getAsOptions(limit: 2, page: 1))->toBe(['CA' => 'Canada', 'FR' => 'France'])
        ->and(Country::getAsOptions(limit: 2, page: 2))->toBe(['US' => 'United States']);
});

test('getAsOptions modifyQuery filters and takes over ordering', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'is_active' => true]);
    Country::query()->create(['id' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada', 'is_active' => false]);

    $options = Country::getAsOptions(modifyQuery: fn ($query) => $query->where('is_active', true)->orderByDesc('name'));

    expect($options)->toBe(['US' => 'United States']);
});

test('State::getAsOptionsWhereCountryIs accepts a Country model or its id string identically', function () {
    seedTwoCountriesWithStatesAndCities();

    $byString = State::getAsOptionsWhereCountryIs('US');
    $byModel = State::getAsOptionsWhereCountryIs(Country::query()->find('US'));

    expect($byString)->toBe(['US-CA' => 'California', 'US-TX' => 'Texas'])
        ->and($byModel)->toBe($byString);
});

test('State::getAsOptionsWhereCountryIs modifyQuery composes with the built-in filter and owns ordering', function () {
    seedTwoCountriesWithStatesAndCities();

    $options = State::getAsOptionsWhereCountryIs('US', modifyQuery: fn ($query) => $query->orderByDesc('name'));

    expect($options)->toBe(['US-TX' => 'Texas', 'US-CA' => 'California']);
});

test('State::getAsOptionsWhereCountryIs accepts a closure that filters on a non-key identifier', function () {
    seedTwoCountriesWithStatesAndCities();

    $options = State::getAsOptionsWhereCountryIs(
        fn ($query) => $query->whereHas('country', fn ($country) => $country->where('iso3', 'USA'))
    );

    expect($options)->toBe(['US-CA' => 'California', 'US-TX' => 'Texas']);
});

test('City::getAsOptionsWhereCountryIs filters the local country_id column directly', function () {
    seedTwoCountriesWithStatesAndCities();

    expect(City::getAsOptionsWhereCountryIs('CA'))->toBe([4 => 'Toronto']);
});

test('City::getAsOptionsWhereStateIs accepts a State model or its id string identically', function () {
    seedTwoCountriesWithStatesAndCities();

    $byString = City::getAsOptionsWhereStateIs('US-CA');
    $byModel = City::getAsOptionsWhereStateIs(State::query()->find('US-CA'));

    expect($byString)->toBe([1 => 'Los Angeles', 2 => 'San Francisco'])
        ->and($byModel)->toBe($byString);
});

test('City::getAsOptionsWhereStateAndCountryAre filters on both columns together', function () {
    seedTwoCountriesWithStatesAndCities();

    expect(City::getAsOptionsWhereStateAndCountryAre('US-CA', 'US'))->toBe([1 => 'Los Angeles', 2 => 'San Francisco'])
        // A state id that belongs to a different country than the one given never matches.
        ->and(City::getAsOptionsWhereStateAndCountryAre('US-CA', 'CA'))->toBe([]);
});

test('Timezone::getAsOptionsWhereCountryIs filters via the country_timezone pivot', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);
    Country::query()->create(['id' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada']);

    Timezone::query()->create(['id' => 'America/Chicago', 'abbreviation' => 'CST', 'name' => 'Central Standard Time']);
    Timezone::query()->create(['id' => 'America/Los_Angeles', 'abbreviation' => 'PST', 'name' => 'Pacific Standard Time']);
    Timezone::query()->create(['id' => 'America/Toronto', 'abbreviation' => 'EST', 'name' => 'Eastern Standard Time']);

    Country::query()->find('US')->timezones()->attach(['America/Chicago', 'America/Los_Angeles']);
    Country::query()->find('CA')->timezones()->attach('America/Toronto');

    expect(Timezone::getAsOptionsWhereCountryIs('US'))->toBe([
        'America/Chicago' => 'Central Standard Time',
        'America/Los_Angeles' => 'Pacific Standard Time',
    ]);
});

test('Timezone::getAsOptionsWhereCountryIs still resolves when the countries dataset is disabled', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);
    Timezone::query()->create(['id' => 'America/Chicago', 'abbreviation' => 'CST', 'name' => 'Central Standard Time']);
    Country::query()->find('US')->timezones()->attach('America/Chicago');

    Schema::dropIfExists(config('locale.table_names.countries'));
    config(['locale.datasets.countries' => false]);

    // The pivot's own country_id is used directly -- no countries table needed.
    expect(Timezone::getAsOptionsWhereCountryIs('US'))->toBe(['America/Chicago' => 'Central Standard Time']);
});

test('Subregion::getAsOptionsWhereRegionIs filters on region_id', function () {
    Region::query()->create(['id' => 'americas', 'name' => 'Americas']);
    Region::query()->create(['id' => 'europe', 'name' => 'Europe']);

    Subregion::query()->create(['id' => 'northern-america', 'region_id' => 'americas', 'name' => 'Northern America']);
    Subregion::query()->create(['id' => 'southern-europe', 'region_id' => 'europe', 'name' => 'Southern Europe']);

    expect(Subregion::getAsOptionsWhereRegionIs('americas'))->toBe(['northern-america' => 'Northern America'])
        ->and(Subregion::getAsOptionsWhereRegionIs(Region::query()->find('europe')))->toBe(['southern-europe' => 'Southern Europe']);
});

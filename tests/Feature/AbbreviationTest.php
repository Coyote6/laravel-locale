<?php

use Coyote6\LaravelLocale\Models\Country;
use Coyote6\LaravelLocale\Models\State;
use Coyote6\LaravelLocale\Models\Timezone;

test('country abbreviation and abbr default to the id (iso2) field', function () {
    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect($country->abbreviation)->toBe('US')
        ->and($country->abbr)->toBe('US');
});

test('country abbreviation is configurable to a different field, e.g. iso3', function () {
    config(['locale.abbreviations.countries' => 'iso3']);

    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    expect($country->abbreviation)->toBe('USA')
        ->and($country->abbr)->toBe('USA');
});

test('state abbreviation defaults to the bare code, not the full composite id', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    $state = State::query()->create([
        'id' => 'US-CA', 'country_id' => 'US', 'code' => 'CA', 'name' => 'California',
    ]);

    expect($state->abbreviation)->toBe('CA')
        ->and($state->abbr)->toBe('CA')
        ->and($state->id)->toBe('US-CA');
});

test('state abbreviation is configurable to the full composite id', function () {
    config(['locale.abbreviations.states' => 'id']);

    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    $state = State::query()->create([
        'id' => 'US-CA', 'country_id' => 'US', 'code' => 'CA', 'name' => 'California',
    ]);

    expect($state->abbreviation)->toBe('US-CA');
});

test('timezone only exposes abbr, not a redundant getAbbreviationAttribute override', function () {
    $timezone = Timezone::query()->create([
        'id' => 'America/Chicago', 'abbreviation' => 'CST', 'name' => 'Central Standard Time',
    ]);

    // The real column value, read normally — not through a custom accessor.
    expect($timezone->abbreviation)->toBe('CST')
        // The trait-provided alias reads the same configured field.
        ->and($timezone->abbr)->toBe('CST')
        ->and(method_exists($timezone, 'getAbbreviationAttribute'))->toBeFalse()
        ->and(method_exists($timezone, 'getAbbrAttribute'))->toBeTrue();
});

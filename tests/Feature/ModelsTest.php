<?php

use Coyote6\LaravelLocale\Models\Country;
use Coyote6\LaravelLocale\Models\Region;
use Coyote6\LaravelLocale\Models\State;
use Coyote6\LaravelLocale\Models\Timezone;
use Illuminate\Support\Facades\Schema;

test('country primary key is the iso2 code, not an autoincrementing id', function () {
    $country = Country::query()->create([
        'id' => 'US',
        'iso3' => 'USA',
        'name' => 'United States',
    ]);

    expect($country->getKey())->toBe('US')
        ->and($country->getKeyType())->toBe('string')
        ->and($country->incrementing)->toBeFalse();
});

test('state primary key is the country+code composite and belongsTo resolves via default conventions', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    $state = State::query()->create([
        'id' => 'US-CA',
        'country_id' => 'US',
        'code' => 'CA',
        'name' => 'California',
    ]);

    expect($state->getKey())->toBe('US-CA')
        ->and($state->country)->not->toBeNull()
        ->and($state->country->id)->toBe('US');
});

test('country timezone belongsToMany pivot resolves both directions', function () {
    Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);

    Timezone::query()->create([
        'id' => 'America/Chicago',
        'abbreviation' => 'CST',
        'name' => 'Central Standard Time',
    ]);

    $country = Country::query()->find('US');
    $country->timezones()->attach('America/Chicago');

    expect($country->timezones()->count())->toBe(1);

    $timezone = Timezone::query()->find('America/Chicago');
    expect($timezone->countries()->count())->toBe(1)
        ->and($timezone->countries()->first()->id)->toBe('US');
});

test('a genuinely nonexistent table (disabled dataset) does not break its relationships', function () {
    // The real scenario this protects against: config('locale.datasets.X')
    // was false when migrate ran, so the table was never created at all —
    // not just "config toggled after the table already existed". Dropping
    // the tables directly here simulates that precisely.
    Schema::dropIfExists(config('locale.table_names.country_timezone'));
    Schema::dropIfExists(config('locale.table_names.timezones'));
    Schema::dropIfExists(config('locale.table_names.states'));
    Schema::dropIfExists(config('locale.table_names.country_language'));
    Schema::dropIfExists(config('locale.table_names.subregions'));

    config([
        'locale.datasets.timezones' => false,
        'locale.datasets.states' => false,
        'locale.datasets.country_languages' => false,
        'locale.datasets.subregions' => false,
    ]);

    $country = Country::query()->create(['id' => 'US', 'iso3' => 'USA', 'name' => 'United States']);
    $region = Region::query()->create(['id' => 'americas', 'name' => 'Americas']);

    expect($country->timezones()->get())->toHaveCount(0)
        ->and($country->timezones)->toHaveCount(0) // dynamic property access too
        ->and($country->languages())->toBe([])
        ->and($country->states()->get())->toHaveCount(0)
        ->and($country->states)->toHaveCount(0)
        ->and($region->subregions()->get())->toHaveCount(0)
        ->and($region->subregions)->toHaveCount(0);

    // whereHas()/whereDoesntHave() must also stay safe — they build their
    // own subqueries independently of a plain ->get() call.
    expect(Country::whereHas('timezones')->count())->toBe(0)
        ->and(Country::whereDoesntHave('timezones')->count())->toBe(1);
});

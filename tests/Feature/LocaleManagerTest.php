<?php

use Coyote6\LaravelLocale\Facades\Locale;

test('language looks up name and direction via Symfony Intl', function () {
    $english = Locale::language('en');

    expect($english->id)->toBe('en')
        ->and($english->name)->toBe('English')
        ->and($english->direction)->toBe('ltr');

    $arabic = Locale::language('ar');

    expect($arabic->direction)->toBe('rtl');
});

test('language returns null for an unknown code', function () {
    expect(Locale::language('not-a-real-code'))->toBeNull()
        ->and(Locale::languageExists('not-a-real-code'))->toBeFalse()
        ->and(Locale::languageExists('en'))->toBeTrue();
});

test('language abbreviation defaults to id, matching the Eloquent models\' property-style access', function () {
    $language = Locale::language('en');

    expect($language->abbreviation)->toBe('en')
        ->and($language->abbr)->toBe('en');
});

test('language resolves an ISO 639-3 code via its ISO 639-1 equivalent', function () {
    $english = Locale::language('eng');

    expect($english)->not->toBeNull()
        ->and($english->id)->toBe('en') // normalized to the form Symfony Intl actually recognizes
        ->and($english->name)->toBe('English');

    expect(Locale::languageExists('eng'))->toBeTrue();
});

test('language returns null for a code with no two-letter equivalent that Symfony Intl also does not recognize', function () {
    expect(Locale::language('cnr'))->toBeNull() // Montenegrin — real example with no two-letter form
        ->and(Locale::languageExists('cnr'))->toBeFalse();
});

test('languages returns every language keyed by code', function () {
    $languages = Locale::languages();

    expect($languages)->toBeArray()
        ->and($languages)->toHaveKey('en')
        ->and($languages['en']->name)->toBe('English');
});

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sync Schedule
    |--------------------------------------------------------------------------
    |
    | How often the locale:sync command re-checks the upstream source for
    | changes. One of: 'daily', 'weekly', 'monthly', 'yearly', 'manual'.
    | 'manual' registers no scheduled task at all — you run `locale:sync`
    | yourself, whenever you want.
    |
    */

    'sync' => [
        'frequency' => env('LOCALE_SYNC_FREQUENCY', 'monthly'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Datasets
    |--------------------------------------------------------------------------
    |
    | Which optional datasets locale:sync populates. All on by default —
    | someone who never reads this file shouldn't be stuck wondering why
    | Country::timezones() comes back empty.
    |
    | @ai
    |		These flags control POPULATION only, never table/column
    |		existence — every migration creates its table (and every
    |		conditional column) unconditionally, regardless of these
    |		values. Flipping one off just means locale:sync skips writing
    |		to an already-existing, empty table, so every relationship and
    |		method in this package (Country::states(), ::timezones(),
    |		::languages(), ::currency(), Region::subregions(), etc.) keeps
    |		working and returns an empty result rather than throwing a
    |		"table doesn't exist" error. This is why none of those methods
    |		contain a config() check of their own — there's nothing for
    |		them to guard against.
    |
    | Languages aren't listed here: they're looked up at runtime via Symfony
    | Intl (see the Locale facade), never imported into a database table at
    | all.
    |
    | @ai
    |		'countries' being toggleable doesn't mean country_id columns on
    |		states/cities/country_timezone/country_language go null when
    |		it's off, the way region_id/currency_id etc. do when THEIR
    |		target dataset is off. Every country_id value is already known
    |		directly from the source's own per-country record — no lookup
    |		against a countries table is ever needed to know it — so
    |		GeoDataImporter keeps writing the real value regardless, and
    |		none of those columns carry a hard foreign key (see their
    |		migrations). Only the Country row itself, and relationships
    |		that require an actual Country row to exist
    |		(State::country()/City::country()/Timezone::countries()/
    |		Currency::countries()), are gated on this flag.
    |
    */

    'datasets' => [
        // Never gates country_id population elsewhere — see the @ai note
        // above. Only gates whether a Country row itself gets written.
        'countries' => true,

        // Currency data comes embedded in the same country records
        // countries itself is built from, but it's still its own toggle —
        // deduplicated into a real table (see Models\Currency,
        // GeoDataImporter::upsertCurrency()), not guaranteed to exist the
        // way countries is.
        'currencies' => true,

        'states' => true,
        'timezones' => true,
        'regions' => true,
        'subregions' => true,

        // ~153,000 rows in the source dataset — the one dataset here
        // that's a real size/import-time tradeoff, not just "off until
        // proven otherwise". Turn off if you don't need city-level data;
        // states are usually enough for address forms and locale/timezone
        // resolution.
        'cities' => true,

        // A separate, less-proven third source (see 'source' block below)
        // with a real ~14% code-resolution gap against Symfony Intl
        // (minor/regional languages only, not mainstream ones). Only
        // stores which language codes a country is linked to
        // (Country::languages()) — no primary/official language yet, see
        // 'source' block below for why.
        'country_languages' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Override any of these in the consuming app's published config if they
    | collide with an existing table or you'd just rather name them
    | differently. 'country_timezone' defaults to Laravel's own pivot-naming
    | convention (singular model names, alphabetical, underscore-joined).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Empty Relation Table
    |--------------------------------------------------------------------------
    |
    | The always-real table Concerns\BuildsEmptyRelations::emptyBelongsToMany()
    | joins against when a belongsToMany relationship's target dataset is
    | disabled — see that trait for why a fake pivot has to be a genuinely
    | existing table. Only belongsToMany needs this; emptyBelongsTo() and
    | emptyHasMany() don't, since both can fake their result entirely within
    | the calling model's own always-existing table (see BuildsEmptyRelations).
    | Defaults to 'states': the second most-likely table in this package to
    | exist (after 'countries', which can't be the default — see below).
    |
    | @ai
    |		'countries' is deliberately not the default here — every
    |		dataset in this package (including 'countries' itself, as of
    |		its own toggle above) can be disabled, so there's no longer a
    |		single table guaranteed to survive every combination.
    |		Country::timezones() is this trait's only real belongsToMany
    |		caller today, and it's called ON a Country instance, so
    |		defaulting to 'countries' would self-join Country against its
    |		own table (an "ambiguous column name" SQL error, not a graceful
    |		empty result) whenever it WAS enabled anyway. If whatever table
    |		is configured here ever matches the calling model's own table,
    |		or its own dataset is disabled, BuildsEmptyRelations falls back
    |		automatically to the first other enabled dataset's table, and
    |		as a last resort to Laravel's own 'migrations' table — every
    |		real Laravel app has one, unlike e.g. 'users', which isn't
    |		guaranteed (auth-less apps, non-standard user tables).
    |
    */

    'empty_relation_table' => 'states',

    'table_names' => [
        'countries' => 'countries',
        'currencies' => 'currencies',
        'states' => 'states',
        'timezones' => 'timezones',
        'country_timezone' => 'country_timezone',
        'regions' => 'regions',
        'subregions' => 'subregions',
        'cities' => 'cities',
        'country_language' => 'country_language',
    ],

    /*
    |--------------------------------------------------------------------------
    | Abbreviation Attributes
    |--------------------------------------------------------------------------
    |
    | Which underlying field the abbreviation/abbr accessors read from, per
    | model. Countries and states are the ones with a real "there's more than
    | one legitimate default" case — e.g. you may want countries.abbreviation
    | to resolve iso3 instead of iso2, or states.abbreviation to resolve the
    | full "US-CA" id instead of the bare "CA" code.
    |
    | Note: Timezone only gets getAbbrAttribute(), never getAbbreviationAttribute()
    | — its 'abbreviation' column already carries that exact name, so the
    | accessor would just be a redundant pass-through (see Concerns/HasAbbreviation).
    |
    | Regions/subregions/cities aren't listed here — none of the three has a
    | natural code/iso field for an abbreviation to mean anything.
    |
    */

    'abbreviations' => [
        'countries' => 'id',
        'currencies' => 'id',
        'states' => 'code',
        'timezones' => 'abbreviation',
        'languages' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Source
    |--------------------------------------------------------------------------
    |
    | Countries/currencies/states/timezones come from the latest GitHub
    | Release of dr5hn/countries-states-cities-database (ODbL v1.0 —
    | commercial use and modification are permitted; attribution is
    | required, see this package's README). The "latest" release URL always
    | resolves to the current release, so locale:sync never needs to track
    | a version number itself.
    |
    | Regions/subregions have no release asset of their own (only the
    | combined countries+states+cities export does) — they're pulled from
    | the repository's default branch instead, a live branch head rather
    | than a stable tagged release, so it's a slightly weaker freshness
    | guarantee than everything else here.
    |
    | Country-language links come from a different source entirely —
    | mledoze/countries (also ODbL v1.0), since neither dr5hn nor Symfony
    | Intl has any country-to-language mapping at all (checked both
    | directly: dr5hn's country records have no language field, and Symfony
    | Intl's data is all disconnected code -> name/region lookups with no
    | territory linkage). Same "pulled from the default branch, not a
    | tagged release" caveat as regions/subregions above. Language codes
    | are ISO 639-3 here (vs. mostly-639-1 everywhere else in this
    | package) — resolved to whatever form Symfony Intl actually recognizes
    | via LocaleManager::resolveCode() at import time; codes that don't
    | resolve in either form are skipped, not imported.
    |
    | @ai
    |		No 'primary'/official language field exists in this source —
    |		it's a flat, unordered list per country. Deliberately NOT
    |		building Country::language() (singular) or a primary_language_code
    |		column against this data — "first listed = primary" would be an
    |		arbitrary, unverified heuristic. Revisit in a future release if
    |		a source that actually marks official status turns up; until
    |		then only Country::languages() (plural) exists.
    |
    */

    'source' => [
        'geo_release_url' => 'https://github.com/dr5hn/countries-states-cities-database/releases/latest/download/json-countries+states+cities.json.gz',
        'regions_url' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/regions.json',
        'subregions_url' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/subregions.json',
        'languages_url' => 'https://raw.githubusercontent.com/mledoze/countries/master/countries.json',
    ],

];

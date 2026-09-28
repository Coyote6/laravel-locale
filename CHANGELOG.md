# Changelog

All notable changes to `coyote6/laravel-locale` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Real, conditional foreign keys on every cross-table column that was
  previously a bare, unenforced value: `states.country_id`,
  `cities.country_id`/`state_id`/`timezone_id`,
  `countries.currency_id`/`region_id`/`subregion_id`,
  `subregions.region_id`, and `country_timezone.country_id`/
  `country_language.country_id` (alongside the existing
  `country_timezone.timezone_id` FK). Each is added only when its target
  dataset is *also* enabled at the moment that migration runs — never
  against a table that might not exist — cascading on delete for a required
  column, nulling on delete for a nullable one. `locale.php`'s own
  `foreign_key_constraints` sqlite setting in `tests/TestCase.php` is now
  explicitly `true` (previously absent, which meant sqlite silently never
  enforced any FK at all, including the one that already existed).

- `Country`, `Currency`, `Region`, `State`, `Timezone`, `Subregion`, and
  `City` now compose `coyote6/laravel-base`'s `GetAsOptions`, so every one
  exposes `Model::getAsOptions(string $key = 'id', string $field = 'name',
  int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array` —
  `$key`/`$field` must be real columns (e.g. `getAsOptions('iso3')`,
  `getAsOptions('code')`); the base trait plucks at the query level, so the
  `abbr`/`abbreviation` accessors from `Concerns\HasAbbr`/`HasAbbreviation`
  aren't valid `$key`/`$field` values.
- `Concerns\BuildsFilteredOptions` — the composition engine behind this
  package's new relationship-scoped option-list helpers. Its
  `optionFilter()` normalizes a helper's first argument (a related model, its
  key as a string, or a closure that filters the query itself) into a filter
  closure; `getAsOptionsFiltered()` composes that filter with the caller's
  own `$modifyQuery`, preserving `GetAsOptions`' rule that an explicit
  `$modifyQuery` owns ordering.
- `Concerns\HasCountryColumnOptions` and the new helpers built on it:
  `State::getAsOptionsWhereCountryIs()`,
  `City::getAsOptionsWhereCountryIs()` /
  `City::getAsOptionsWhereStateIs()` /
  `City::getAsOptionsWhereStateAndCountryAre()`,
  `Timezone::getAsOptionsWhereCountryIs()` (filters the `country_timezone`
  pivot's own `country_id` directly, so it resolves even with the countries
  dataset disabled), and `Subregion::getAsOptionsWhereRegionIs()`. Each takes
  a `Model|string|Closure` first argument — a related model, that model's id
  as a string (matched directly against the always-populated local foreign
  key column, no lookup), or a closure for matching on anything else (e.g. an
  `iso3` code) — followed by the full `getAsOptions()` parameter list.
  `Currency` does not get a `getAsOptionsWhereCountryIs()`: a country has
  exactly one currency, so it would return at most one option — see the `@ai`
  note on `Currency::countries()`.
- `State::getAsOptions()` / `City::getAsOptions()` now default `$field` to a
  richer, disambiguated label instead of the bare `name` — `"US - California"`
  for `State`, `"US - CA - Los Angeles"` for `City` (skipping the state
  segment entirely for a city with no `state_id`, e.g. `"FR - Paris"`, rather
  than a dangling separator). Built as a driver-aware `DB::raw()` `Expression`
  (`Concerns\BuildsFilteredOptions::concatSql()` / `substrFromSql()`), not a
  `Closure` — every column it needs (`country_id`, `state_id`) is already
  denormalized onto the row, so no join and no eager-loaded relation are
  needed, keeping the default call as cheap as the old bare-`name` one. An
  explicit `$field` still opts back into a plain column. **Both models are
  still unlimited by default** — pass `$field`/`$modifyQuery`/`$limit`, this
  doesn't change either model's own size (`City` runs to ~153,000 rows).
- `.github/workflows/tests.yml` — this package had no CI at all before this;
  a sqlite matrix (`php: 8.3/8.4` × `dependency-version: lowest/highest`)
  plus a dedicated `test-mariadb` job against a real `mariadb:11` service
  container, mirroring `coyote6/laravel-base`'s CI.

### Fixed

- `locale:sync` imported countries (which write `region_id`/`subregion_id`
  whenever those datasets are enabled) *before* regions/subregions
  themselves existed. Harmless while those columns carried no constraint;
  now that they carry a real conditional foreign key (see Added, above), it
  would have broken every sync on any driver that enforces foreign keys.
  `SyncLocaleData` now imports regions/subregions first.

### Changed

- Requires `coyote6/laravel-base` `^2.2` (was `^2.0`), for the unified
  `GetAsOptions::getAsOptions()` signature (`^2.1`) and `$field`'s
  `Expression`/`Closure` support (`^2.2`, now used by `State`/`City`'s new
  default labels above).
- `tests/TestCase.php`'s `defineEnvironment()` no longer hardcodes sqlite —
  it now honors `DB_CONNECTION`/`DB_HOST`/etc. when set (the CI MariaDB leg),
  falling back to the same in-memory sqlite connection as before when unset
  (every local/default `vendor/bin/pest` run is unaffected).

## [1.0.0] - 2026-08-27

Initial release.

### Added

- `Country`, `Currency`, `State`, `Timezone`, `Region`, `Subregion`, `City`
  models. Countries/currencies/states/timezones are keyed by natural codes
  (ISO 3166-1 alpha-2, ISO 4217, ISO 3166-2, IANA zone name) rather than
  surrogate ids; `City` is the one exception, keyed by the source's own
  stable numeric id since no ISO-style code exists for cities.
- `locale:sync` Artisan command, importing from
  `dr5hn/countries-states-cities-database`'s latest release — no data
  bundled in this package itself. Cities (~153,000 rows) are written in
  chunked query-builder upserts rather than per-row Eloquent calls.
- Configurable sync schedule (`daily`/`weekly`/`monthly`/`yearly`/`manual`),
  configurable dataset toggles (`countries`/`currencies`/`states`/`timezones`/
  `regions`/`subregions`/`cities`/`country_languages`, all on by default —
  yes, `countries` too), configurable table names, and configurable
  `abbreviation`/`abbr` source fields per model.
- Disabling a dataset before `php artisan migrate` genuinely prevents that
  table from being created — the config flags mean what they say. Every
  relationship in this package stays safe to call anyway, via one of three
  mechanisms depending on the relationship:
  - Most `belongsTo`-style relationships (`Country::currency()`,
    `City::state()`/`::timezone()`, `Subregion::region()`) need no special
    handling since Eloquent already skips the query when the local foreign
    key is null, and `GeoDataImporter` only ever writes a real value into
    those columns when the target dataset is also enabled.
  - `country_id`-based `belongsTo` relationships (`State::country()`,
    `City::country()`) are the exception: `country_id` stays populated with
    its real value regardless of whether `countries` is enabled (already
    known directly from the source record, no lookup needed), so they can't
    rely on a null FK — they check `config('locale.datasets.countries')`
    themselves and fall back to `Concerns\BuildsEmptyRelations::emptyBelongsTo()`.
  - `hasMany`/`belongsToMany` relationships (`Country::states()`,
    `::timezones()`, `Region::subregions()`, `Timezone::countries()`,
    `Currency::countries()`) can't rely on either trick, since a `WHERE`
    clause can't save a query whose *other* table doesn't exist — those
    check config themselves and fall back to a real relation scoped to an
    always-existing table with an always-false condition when disabled.

  All three are confirmed safe under `->get()`, dynamic property access,
  `with()` eager loading, `whereHas()`, and `whereDoesntHave()` — not just
  assumed (`Concerns\BuildsEmptyRelations`). `belongsToMany`'s fake pivot
  table is configurable (`empty_relation_table`, default `states`) and
  self-correcting: it automatically falls back to the next enabled dataset
  table (and ultimately to `migrations`) if the configured value would
  self-join the calling model or its own dataset is disabled.
- `Locale` facade for language lookups via Symfony Intl — no database table,
  always current with the installed `symfony/intl` version.
  `LocaleManager::language()`/`languageExists()` accept either ISO 639-1
  (two-letter) or ISO 639-2/3 (three-letter) codes, transparently resolving
  a three-letter code to its two-letter equivalent when Symfony Intl
  indexes it that way.
- Timezone import validates every zone name against this PHP installation's
  `DateTimeZone::listIdentifiers()`, skipping (and reporting) any the
  source provides that PHP's tzdata doesn't recognize.
- `Country::currency()` — a real `belongsTo`, unlike `languages()` below,
  since the source embeds complete currency data per country and it's
  deduplicated into its own table the same way `Timezone` is (many
  countries share one currency, e.g. every Eurozone country uses EUR).
  Singular only — the source never gives more than one currency per
  country, so there's no primary/secondary ambiguity to resolve. `currencies`
  is a configurable dataset like `states`/`timezones`/etc. — no hard foreign
  key on `countries.currency_id`, populated only when enabled, same pattern
  as everything else.
- `Country::languages()` and the `country_language` pivot, sourced from
  `mledoze/countries` — a separate source from everything else in this
  package, since neither `dr5hn` nor Symfony Intl has any
  country-to-language mapping. Language codes that don't resolve against
  Symfony Intl are skipped and reported, same as timezones (~14% of the
  source's codes, all minor/regional languages).
  **No primary/official language yet** — the source has no marker for it;
  revisit in a future release if a source that does turns up (see the `@ai`
  note in `config/locale.php`'s `source` block).

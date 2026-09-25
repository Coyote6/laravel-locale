# coyote6/laravel-locale

Countries, currencies, states, timezones, regions/subregions, cities, and
country-language links for Laravel — plus language lookups via
[Symfony Intl](https://symfony.com/doc/current/components/intl.html).

**This package stores no data of its own.** It ships migrations, models, and an
importer — the importer pulls live from
[`dr5hn/countries-states-cities-database`](https://github.com/dr5hn/countries-states-cities-database)
(and, for country-language links, [`mledoze/countries`](https://github.com/mledoze/countries))
on whatever schedule you configure (or on demand), and writes the result into
your own application's database. Nothing is bundled or committed here.
Language data itself isn't imported at all — it's looked up at runtime
straight from `symfony/intl`, so there's nothing to keep in sync for that one.

## Installation

```bash
composer require coyote6/laravel-locale
php artisan migrate
php artisan locale:sync
```

Publish the config if you want to customize anything below:

```bash
php artisan vendor:publish --tag=locale-config
```

## What gets imported

Everything is on by default, so `php artisan migrate && php artisan locale:sync`
with zero configuration gets you fully populated data — no reading this file
first required just to find out why a relationship comes back empty.

| Table | Notes |
|---|---|
| `countries` | keyed by ISO 3166-1 alpha-2 (`id` = `"US"`) |
| `currencies` | keyed by ISO 4217 (`id` = `"EUR"`); deduplicated, so every Eurozone country shares one row |
| `states` | keyed by ISO 3166-2 (`id` = `"US-CA"`) |
| `timezones` | keyed by IANA zone name (`id` = `"America/Chicago"`) |
| `country_timezone` | pivot, follows `timezones` |
| `regions` | continents; no natural code, keyed by a slug |
| `subregions` | sub-continents; same |
| `cities` | ~153,000 rows — keyed by the source's own numeric id, since (unlike countries/states) there's no ISO-style code for cities to key on instead |
| `country_language` | pivot — which language codes a country is linked to, from a different source entirely (see [Country languages](#country-languages)) |

## Turning things off

Every dataset — including `countries` itself — lives in `config/locale.php`'s
`datasets` block:

```php
'datasets' => [
    'countries' => true,
    'currencies' => true,
    'states' => true,
    'timezones' => true,
    'regions' => true,
    'subregions' => true,
    'cities' => true,
    'country_languages' => true,
],
```

**Setting one to `false` before running `php artisan migrate` means that
table is never created at all** — this genuinely prevents an unneeded table
from existing, not just from being populated. Every relationship and method
in this package still stays safe to call regardless: `Country::timezones()`,
`::states()`, `::languages()`, `Region::subregions()` all return an empty
result rather than throwing, even when their underlying table doesn't exist.

That safety works three different ways depending on the relationship, and
it's worth knowing which is which:

- **Most `belongsTo` relationships** (`Country::currency()`,
  `City::state()`/`::timezone()`, `Subregion::region()`) need no special
  handling at all — Eloquent already skips the query entirely whenever the
  local foreign key column is `null`, which it always is on a row written
  while the target dataset was disabled (`GeoDataImporter` only ever writes
  a real value into `countries.currency_id`, `cities.state_id`,
  `cities.timezone_id`, `countries.region_id`/`subregion_id` etc. when that
  specific dataset is also enabled — otherwise it leaves the column null on
  purpose).
- **`country_id`-based `belongsTo` relationships** (`State::country()`,
  `City::country()`) are the one exception to the null-FK trick above:
  `country_id` is always populated with its real value regardless of
  whether `countries` itself is enabled, since the code is already known
  directly from the same source record — no lookup against a countries
  table is ever needed to know it. That means these can't rely on a null FK
  the way everything else does, so they check
  `config('locale.datasets.countries')` themselves and, when it's off, fall
  back to `Concerns\BuildsEmptyRelations::emptyBelongsTo()` — a real
  `BelongsTo` scoped to the model's own always-existing table with an
  always-false condition, the same trick described below for
  `hasMany`/`belongsToMany`, just applied to `belongsTo` instead. Confirmed
  safe under `->get()`, dynamic property access, `with()` eager loading,
  `whereHas()`, and `whereDoesntHave()`.
- **`hasMany`/`belongsToMany` relationships** (`Country::states()`,
  `::timezones()`, `Region::subregions()`, `Timezone::countries()`,
  `Currency::countries()`) can't rely on that trick — a
  `WHERE` clause can't save you when the table on the *other* side of the
  query doesn't exist; the database has to resolve the table name before it
  will even parse the query, regardless of what would ultimately match. So
  these methods check `config('locale.datasets.*')` themselves and, when
  disabled, return a real `HasMany`/`BelongsToMany` scoped to the model's
  own always-existing table with an always-false condition — confirmed
  (not assumed) to behave correctly under `->get()`, dynamic property
  access, `with()` eager loading, `whereHas()`, and `whereDoesntHave()`; see
  `Concerns\BuildsEmptyRelations`.

  `belongsToMany`'s fake relation additionally needs a *pivot* table that
  genuinely exists — `config('locale.empty_relation_table')` (`states` by
  default) controls which one. It's automatically skipped in favor of the
  next enabled table in `Concerns\BuildsEmptyRelations::EMPTY_RELATION_DATASETS`
  (falling back to Laravel's own `migrations` table as a last resort) if
  it's ever the *calling* model's own table (would self-join) or its
  dataset happens to be disabled — you don't have to hand-pick a value that
  works for every model and every combination of disabled datasets.
- **`Country::languages()`** isn't a typed relation at all (see
  [Country languages](#country-languages) below), so it just checks config
  directly and returns `[]` — no trick needed.

Reasons you might turn one off:

- **`countries`** — an app that only needs, say, `states`/`cities` for
  address forms (and already has its own country list/model) can skip this
  package's copy entirely. `country_id` columns stay populated everywhere
  regardless (see above), so nothing else in this package loses data by
  turning it off — only `Country::currency()`/`::states()`/`::timezones()`/
  `::languages()` and the reverse `State::country()`/`City::country()`/
  `Timezone::countries()`/`Currency::countries()` become unreachable, since
  there's no Country row for any of them to resolve.
- **`cities`** — ~153,000 rows is a real size/import-time cost. `locale:sync`
  writes them in batches of 500 via the query builder rather than one
  Eloquent call per row, but it's still the slowest part of a sync by far.
- **`regions`/`subregions`** — the source has no versioned release asset for
  these (only the combined countries+states+cities export does), so they're
  pulled from the repository's default branch instead — a live branch head
  rather than a stable tagged release, a slightly weaker freshness guarantee
  than everything else here.
- **`country_languages`** — a separate, less-proven third source with a real
  ~14% code-resolution gap against Symfony Intl (minor/regional languages
  only — see [Country languages](#country-languages) below).

## Primary keys are natural codes, not surrogate ids

Countries, currencies, states, and timezones are keyed by their real-world
code rather than an auto-incrementing integer, specifically so foreign keys
elsewhere in your app stay legible — `devices.country_id = 'US'` reads a lot
better than `devices.country_id = 231`. `states.id` is the actual ISO 3166-2
subdivision code (`US-CA`), pulled directly from the source rather than
assembled by hand.

Cities are the one exception: there's no ISO-style code for a city the way
there is for a country or state, so `cities.id` is the source's own stable
numeric id instead. `cities.country_id`/`cities.state_id`/`cities.timezone_id`
still read naturally, though, since they reference the natural-keyed tables
above.

## Abbreviation accessors

Every model exposes `->abbreviation` and `->abbr` (except `Timezone`, which
only gets `->abbr` — see below), reading from a configurable field per model:

```php
// config/locale.php
'abbreviations' => [
    'countries' => 'id',          // -> iso2, e.g. "US". Override to 'iso3' for "USA".
    'currencies' => 'id',         // -> e.g. "EUR"
    'states' => 'code',           // -> bare code, e.g. "CA". Override to 'id' for "US-CA".
    'timezones' => 'abbreviation', // -> e.g. "CST"
    'languages' => 'id',          // -> e.g. "en"
],
```

`Timezone` doesn't get a `getAbbreviationAttribute()` override: its
`abbreviation` column already carries that exact name, so the accessor would
just be a pass-through of itself. `Region`/`Subregion`/`City` aren't in this
config at all — none of the three has a natural code/iso field for an
abbreviation to mean anything.

## Option lists

Every model composes [`coyote6/laravel-base`](https://github.com/Coyote6/laravel-base)'s
`GetAsOptions`, so `Model::getAsOptions()` gives you a select-ready
`$key => $field` list, ordered by `$field` ascending:

```php
Country::getAsOptions();               // ['CA' => 'Canada', 'US' => 'United States', ...]
Country::getAsOptions('iso3');         // ['CAN' => 'Canada', 'USA' => 'United States', ...]
State::getAsOptions(limit: 25, page: 2);
```

`$key`/`$field` must be real columns — `getAsOptions()` plucks at the query
level, so the `abbr`/`abbreviation` accessors from
[abbreviation accessors](#abbreviation-accessors) above aren't valid values
here; pass the concrete column instead (`'iso3'`, `'code'`, `'abbreviation'`).
See `GetAsOptions` for the full `$key`/`$field`/`$limit`/`$page`/`$modifyQuery`
signature.

**`State` and `City` default `$field` to a richer, disambiguated label**
instead of the bare `name` — a plain state/city name reads ambiguously once a
list mixes rows from more than one country:

```php
State::getAsOptions(); // ['US-CA' => 'US - California', 'US-TX' => 'US - Texas', 'CA-ON' => 'CA - Ontario', ...]
City::getAsOptions();  // ['US - CA - Los Angeles', 'US - CA - San Francisco', 'FR - Paris', ...] (no dangling separator when a city has no state)
```

Pass an explicit `$field` (a column, a `DB::raw()` `Expression`, or a
`Closure`) to opt back into a plain column — nothing else about the method
changes. **Both are still unlimited by default** — `State` runs to a few
thousand rows worldwide, `City` to ~153,000 — so treat a bare, unscoped call
to either as a real cost, not a free convenience: pass `$limit`/`$page`, or
scope first with `getAsOptionsWhereCountryIs()` / `WhereStateIs()` /
`WhereStateAndCountryAre()` below. 153,000 options is never a usable dropdown
regardless of how cheap the underlying query is.

**Relationship-scoped option lists** filter that same list to one country,
state, or region — the first argument accepts the related model, that
model's id as a string, or a closure, followed by the same
`$key`/`$field`/`$limit`/`$page`/`$modifyQuery` parameters as `getAsOptions()`:

```php
State::getAsOptionsWhereCountryIs('US');                    // states.country_id = 'US', direct — no lookup
State::getAsOptionsWhereCountryIs(Country::find('US'));     // same, from a Country instance

City::getAsOptionsWhereCountryIs('US');
City::getAsOptionsWhereStateIs('US-CA');
City::getAsOptionsWhereStateAndCountryAre('US-CA', 'US');

Timezone::getAsOptionsWhereCountryIs('US');                 // via the country_timezone pivot
Subregion::getAsOptionsWhereRegionIs('americas');
```

A string is matched directly against the target's local foreign-key column
(`states.country_id`, `cities.state_id`, the `country_timezone` pivot's own
`country_id`, `subregions.region_id`) — every one of those already holds the
related row's natural code (see
[Primary keys are natural codes](#primary-keys-are-natural-codes-not-surrogate-ids)
above), so no second query and no join is ever needed, even for `City`. To
match on something else — an `iso3` code, a name — pass a closure instead; it
receives the query builder and becomes the filter:

```php
State::getAsOptionsWhereCountryIs(
    fn ($query) => $query->whereHas('country', fn ($country) => $country->where('iso3', 'USA'))
);
```

`Timezone::getAsOptionsWhereCountryIs()` filters the `country_timezone`
pivot's own `country_id` directly rather than going through the `countries()`
relationship, so — like the always-populated `country_id` columns on
`states`/`cities` — it resolves correctly even when the `countries` dataset
is disabled.

`Currency` has no `getAsOptionsWhereCountryIs()`: a country has exactly one
currency (see [Country currency](#country-currency) below), so scoping
options to one country would return at most one option, not a meaningful
dropdown.

## Country currency

```php
$country = Country::find('FR');

$country->currency;        // Currency model, or null if the source had none
$country->currency->name;  // "Euro"
$country->currency->symbol; // "€"

Currency::find('EUR')->countries; // every country that uses it — all Eurozone countries, one query
```

A real `belongsTo` relationship, not a DTO-wrapping method like
[`Country::languages()`](#country-languages) — the source embeds complete
currency data (code, name, symbol) directly in every country record, so
there's a real table to deduplicate into, the same way `Timezone` is. Singular,
not plural: unlike languages, the source only ever gives one currency per
country (verified against all 250 countries), so there's no "primary vs.
other" ambiguity to defer.

## Language lookups

Backed directly by Symfony Intl's official CLDR-sourced data — no import, no
sync, always current with whatever `symfony/intl` version is installed:

```php
use Coyote6\LaravelLocale\Facades\Locale;

Locale::language('en')->name;        // "English"
Locale::language('ar')->direction;   // "rtl"
Locale::languageExists('xx');        // false
Locale::languages();                 // array<string, Language>, keyed by code
```

`direction` (`ltr`/`rtl`) isn't part of Symfony Intl's language data — that's
a script-level (ISO 15924) concern, not a 1:1 language lookup — so it's a
small, self-maintained list in `LocaleManager` rather than sourced data. The
set of RTL languages is short and essentially static, unlike everything else
in this package.

`LocaleManager::language()`/`languageExists()` accept either a language's
ISO 639-1 (two-letter) or ISO 639-2/3 (three-letter) code — Symfony Intl
itself only indexes most languages by their two-letter form, so a three-letter
code is transparently resolved to its two-letter equivalent when one exists:

```php
Locale::language('eng')->id; // "en" — normalized, not "eng"
```

## Country languages

```php
$country = Country::find('US');

$country->languages(); // array<Language> — resolved live via Locale::language()
```

This isn't a real Eloquent relationship (`belongsToMany`, `with()`,
eager-loading) — there's no `Language` Eloquent model to relate to, since
language data is never stored (see [Language lookups](#language-lookups)
above). `Country::languages()` reads the `country_language` pivot for this
country's stored language *codes*, then resolves each one through the same
`Locale::language()` call you'd make directly.

The codes come from [`mledoze/countries`](https://github.com/mledoze/countries)
— a different source from everything else in this package, since neither
`dr5hn` nor Symfony Intl has any country-to-language mapping at all (checked
both directly). Its codes are ISO 639-3, most of which need the alpha-3 → alpha-2
fallback described above to resolve. Checked empirically against 153 unique
codes across all 250 countries: 132 resolve (86%), 21 don't — all minor or
regional languages (Dari, Montenegrin, several Berber/African/Pacific
dialects), none of the mainstream ones. `locale:sync` skips and reports
anything that doesn't resolve, same as timezones.

**No primary/official language yet — deferred, not forgotten.** `mledoze/countries`
gives a flat, unordered list per country with no marker for which language
(if any) is official. Treating "first listed" as the primary would be an
arbitrary, unverified heuristic, so there's no `Country::language()`
(singular) method and no `primary_language_code` column. **Revisit this in a
future release** if a source that actually marks official/primary status
turns up — see the `@ai` note in `config/locale.php`'s `source` block and in
`Country::languages()`'s own doc comment.

## Syncing data

```bash
php artisan locale:sync
```

Also scheduled automatically per `config('locale.sync.frequency')`:
`daily`, `weekly`, `monthly` (default), `yearly`, or `manual` (no automatic
schedule registered at all — you run the command yourself).

### A known data-quality gap: not every source timezone is valid PHP

The source data and PHP both draw from the IANA time zone database, but their
snapshots don't always line up — a handful of zone names the source provides
have since been merged into a canonical replacement in more recent tzdata
releases (e.g. `Europe/Kiev` → `Europe/Kyiv` after Ukraine's 2022 renaming, a
few Canadian zones consolidated into `America/Toronto`). At last check this
affected about 2.6% of zone names in the dataset.

`locale:sync` validates every zone name against this PHP installation's own
`DateTimeZone::listIdentifiers()` before importing it. Anything that fails is
**skipped, not imported**, and reported as a warning in the command's output
— never silently included as a value that would throw the moment your app
does `new DateTimeZone($zone)`.

## Data sources & attribution

- **Countries/currencies/states/timezones/regions/subregions**:
  [`dr5hn/countries-states-cities-database`](https://github.com/dr5hn/countries-states-cities-database),
  licensed [ODbL v1.0](https://opendatacommons.org/licenses/odbl/1-0/).
  Commercial use and modification are permitted; attribution is required
  (this README is it), and derivatives of the *data itself*, if redistributed,
  must carry the same license — this doesn't apply to an application merely
  built on top of it.
- **Languages**: [Symfony Intl](https://symfony.com/doc/current/components/intl.html),
  sourced from the [Unicode CLDR](https://cldr.unicode.org/) and the
  [Library of Congress ISO 639-2 Registration Authority](https://www.loc.gov/standards/iso639-2/).
- **Country-language links**: [`mledoze/countries`](https://github.com/mledoze/countries),
  also licensed ODbL v1.0 — same terms as above.

## License

MIT. See [LICENSE](LICENSE).

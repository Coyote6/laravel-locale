<?php

namespace Coyote6\LaravelLocale\Support;

use Coyote6\LaravelLocale\Facades\Locale;
use Coyote6\LaravelLocale\Models\Country;
use Coyote6\LaravelLocale\Models\Currency;
use Coyote6\LaravelLocale\Models\Region;
use Coyote6\LaravelLocale\Models\State;
use Coyote6\LaravelLocale\Models\Subregion;
use Coyote6\LaravelLocale\Models\Timezone;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Pulls countries/states/timezones/cities/regions/subregions from
// dr5hn/countries-states-cities-database and upserts them into this
// package's own tables. Nothing from the source is ever committed into this
// package itself — every run re-fetches live.
class GeoDataImporter
{


    // Cities Chunking
    //
    // Cities are upserted in batches via the query builder, not one
    // Eloquent call per row — ~153,000 rows in the source.
    protected const CITY_CHUNK_SIZE = 500;

    // Timezone Validation State
    /** @var array<string, true> zone names already resolved as valid this run, to avoid rechecking */
    protected array $validatedZones = [];

    /** @var list<string> zone names the source provided that PHP's own tzdata doesn't recognize */
    protected array $invalidZones = [];

    /** @var list<string> language codes the source provided that Symfony Intl doesn't recognize in any form */
    protected array $invalidLanguageCodes = [];

    protected int $citiesImported = 0;


    // Import Geo Data
    //
    // Downloads the latest countries+states+cities release, then upserts
    // countries (always), timezones (if enabled), and states/cities (each
    // independently, if enabled — see @ai note).
    //
    // @ai
    //		States are traversed regardless of whether config('locale.datasets.states')
    //		is on, because cities are only reachable by walking the source's
    //		nested country -> states -> cities structure — persisting a
    //		states row and persisting cities under that state are gated
    //		independently, so 'cities' on with 'states' off still works.
    //
    // @see upsertCountry()
    // @see upsertState()
    // @see upsertCities()
    // @see upsertTimezone()
    //
    // @return void
    //
    public function importGeoData(): void
    {
        $countries = $this->fetchGeoRelease();
        $syncedAt = now();

        $importStates = config('locale.datasets.states');
        $importCities = config('locale.datasets.cities');

        foreach ($countries as $country) {
            $this->upsertCountry($country, $syncedAt);

            if (config('locale.datasets.timezones')) {
                foreach ($country['timezones'] ?? [] as $timezone) {
                    $this->upsertTimezone($country, $timezone, $syncedAt);
                }
            }

            if (! $importStates && ! $importCities) {
                continue;
            }

            foreach ($country['states'] ?? [] as $state) {
                if ($importStates) {
                    $this->upsertState($country, $state, $syncedAt);
                }

                if ($importCities) {
                    $this->upsertCities($country, $state, $state['cities'] ?? [], $syncedAt);
                }
            }
        }
    }


    // Import Regions And Subregions
    //
    // Downloads regions/subregions from the source repository's default
    // branch (see config/locale.php's 'source' block for why this isn't a
    // versioned release asset like everything else) and upserts them,
    // each gated by its own config('locale.datasets.*') flag.
    //
    // @ai
    //		Subregions reference their parent region by the source's own
    //		numeric id, not by name — regionSlugsBySourceId maps that
    //		numeric id to our own slug id as regions import, so subregions
    //		can resolve it in the same run.
    //
    // @return void
    //
    public function importRegionsAndSubregions(): void
    {
        $syncedAt = now();
        $regionSlugsBySourceId = [];

        if (config('locale.datasets.regions')) {
            foreach ($this->fetchJson(config('locale.source.regions_url')) as $region) {
                $slug = Str::slug($region['name']);
                $regionSlugsBySourceId[$region['id']] = $slug;

                Region::query()->updateOrCreate(
                    ['id' => $slug],
                    [
                        'name' => $region['name'],
                        'translations' => $region['translations'] ?? [],
                        'wikidata_id' => $region['wikiDataId'] ?? null,
                        'synced_at' => $syncedAt,
                    ],
                );
            }
        }

        if (config('locale.datasets.subregions')) {
            foreach ($this->fetchJson(config('locale.source.subregions_url')) as $subregion) {
                Subregion::query()->updateOrCreate(
                    ['id' => Str::slug($subregion['name'])],
                    [
                        'region_id' => $regionSlugsBySourceId[$subregion['region_id']] ?? null,
                        'name' => $subregion['name'],
                        'translations' => $subregion['translations'] ?? [],
                        'wikidata_id' => $subregion['wikiDataId'] ?? null,
                        'synced_at' => $syncedAt,
                    ],
                );
            }
        }
    }


    // Import Country Languages
    //
    // Downloads mledoze/countries (see config/locale.php's 'source' block
    // for why this is yet another source, and the deferred-primary-language
    // note) and writes the country_language pivot — which language codes
    // each country is linked to, nothing more.
    //
    // @ai
    //		Loads every existing country id once up front rather than
    //		querying per source record — mledoze includes some
    //		territories/disputed regions (e.g. Kosovo) this package's
    //		countries table may not have, and this filters those out to
    //		match the countries this package actually knows about. Skipped
    //		entirely when config('locale.datasets.countries') is off: there's
    //		no countries table to query, and — country_language.country_id
    //		carrying no hard FK constraint either (see that migration) —
    //		nothing to filter against anyway, so every code the source gives
    //		is written as-is.
    //
    // @see \Coyote6\LaravelLocale\LocaleManager::resolveCode()
    //
    // @return void
    //
    public function importCountryLanguages(): void
    {
        $validCountryIds = config('locale.datasets.countries')
            ? Country::query()->pluck('id')->flip()
            : null;

        foreach ($this->fetchJson(config('locale.source.languages_url')) as $country) {
            $countryId = $country['cca2'] ?? null;

            if ($countryId === null || ($validCountryIds !== null && ! isset($validCountryIds[$countryId]))) {
                continue;
            }

            foreach (array_keys($country['languages'] ?? []) as $sourceCode) {
                $language = Locale::language($sourceCode);

                if ($language === null) {
                    if (! in_array($sourceCode, $this->invalidLanguageCodes, strict: true)) {
                        $this->invalidLanguageCodes[] = $sourceCode;
                    }

                    continue;
                }

                DB::table(config('locale.table_names.country_language'))->upsert(
                    [['country_id' => $countryId, 'language_code' => $language->id]],
                    ['country_id', 'language_code'],
                );
            }
        }
    }


    // Invalid Zones
    //
    // @return list<string> zone names from the source PHP's DateTimeZone doesn't recognize (skipped, not imported)
    //
    public function invalidZones(): array
    {
        return $this->invalidZones;
    }


    // Invalid Language Codes
    //
    // @return list<string> language codes from the source Symfony Intl doesn't recognize in any form (skipped, not imported)
    //
    public function invalidLanguageCodes(): array
    {
        return $this->invalidLanguageCodes;
    }


    // Cities Imported
    //
    // @return int total city rows written across all countries this run
    //
    public function citiesImported(): int
    {
        return $this->citiesImported;
    }


    // Fetch Geo Release
    //
    // Downloads and decompresses the latest countries+states+cities
    // release asset.
    //
    // @return list<array<string, mixed>>
    //
    protected function fetchGeoRelease(): array
    {
        $gzipped = Http::get(config('locale.source.geo_release_url'))->throw()->body();

        return json_decode(gzdecode($gzipped), associative: true, flags: JSON_THROW_ON_ERROR);
    }


    // Fetch Json
    //
    // @param $url string - The URL to fetch and decode as JSON [Ex: https://example.com/data.json]
    //
    // @return list<array<string, mixed>>
    //
    protected function fetchJson(string $url): array
    {
        return Http::get($url)->throw()->json();
    }


    // Upsert Country
    //
    // Writes one country row, unless config('locale.datasets.countries') is
    // off — currency_id, region_id, and subregion_id are each only set when
    // their own dataset is enabled, slugified the same way
    // importRegionsAndSubregions() slugs the region/subregion's own name —
    // so these line up without needing the source's numeric
    // region_id/subregion_id at all. Left null otherwise, same as
    // upsertCities()'s state_id/timezone_id — see @ai note there for why.
    //
    // @ai
    //		upsertCurrency() runs BEFORE the countries-disabled check, not
    //		after — currencies is an independent toggle (config('locale.datasets.currencies')),
    //		so a Currency row still needs writing even when countries itself
    //		is off, the same way upsertState()/upsertCities()/upsertTimezone()
    //		keep running off the country_id they already have inline,
    //		without needing a Country row to exist at all.
    //
    // @see upsertCurrency()
    //
    // @param $country array - One country record from the source release
    // @param $syncedAt \DateTimeInterface - Timestamp to stamp this row with
    //
    // @return void
    //
    protected function upsertCountry(array $country, DateTimeInterface $syncedAt): void
    {
        $currencyId = config('locale.datasets.currencies') ? $this->upsertCurrency($country, $syncedAt) : null;

        if (! config('locale.datasets.countries')) {
            return;
        }

        $attributes = [
            'iso3' => $country['iso3'],
            'name' => $country['name'],
            'native_name' => $country['native'] ?? null,
            'numeric_code' => $country['numeric_code'] ?? null,
            'phone_code' => $country['phonecode'] ?? null,
            'capital' => $country['capital'] ?? null,
            'currency_id' => $currencyId,
            'tld' => $country['tld'] ?? null,
            'region' => $country['region'] ?? null,
            'subregion' => $country['subregion'] ?? null,
            'latitude' => $country['latitude'] ?? null,
            'longitude' => $country['longitude'] ?? null,
            'emoji' => $country['emoji'] ?? null,
            'emoji_unicode' => $country['emojiU'] ?? null,
            'synced_at' => $syncedAt,
        ];

        if (config('locale.datasets.regions') && ! empty($country['region'])) {
            $attributes['region_id'] = Str::slug($country['region']);
        }

        if (config('locale.datasets.subregions') && ! empty($country['subregion'])) {
            $attributes['subregion_id'] = Str::slug($country['subregion']);
        }

        Country::query()->updateOrCreate(['id' => $country['iso2']], $attributes);
    }


    // Upsert Currency
    //
    // Writes one currency row from a country record's embedded currency
    // fields, deduplicated by ISO 4217 code — called once per country, but
    // re-upserting the same code (e.g. every Eurozone country hits "EUR")
    // is a harmless no-op update, same as upsertTimezone()'s equivalent
    // per-country repeats.
    //
    // @param $country array - One country record from the source release
    // @param $syncedAt \DateTimeInterface - Timestamp to stamp this row with
    //
    // @return string|null the currency's ISO 4217 code, for upsertCountry() to store as currency_id — null if this country has no currency in the source
    //
    protected function upsertCurrency(array $country, DateTimeInterface $syncedAt): ?string
    {
        if (empty($country['currency'])) {
            return null;
        }

        Currency::query()->updateOrCreate(
            ['id' => $country['currency']],
            [
                'name' => $country['currency_name'] ?? null,
                'symbol' => $country['currency_symbol'] ?? null,
                'synced_at' => $syncedAt,
            ],
        );

        return $country['currency'];
    }


    // Upsert State
    //
    // Writes one state row, keyed by the source's own iso3166_2 field when
    // present (falls back to assembling it from country+state codes only
    // if that field is ever missing).
    //
    // @param $country array - The parent country record from the source release
    // @param $state array - One state record from the source release
    // @param $syncedAt \DateTimeInterface - Timestamp to stamp this row with
    //
    // @return void
    //
    protected function upsertState(array $country, array $state, DateTimeInterface $syncedAt): void
    {
        $id = $state['iso3166_2'] ?? $country['iso2'].'-'.$state['iso2'];

        State::query()->updateOrCreate(
            ['id' => $id],
            [
                'country_id' => $country['iso2'],
                'code' => $state['iso2'],
                'name' => $state['name'],
                'type' => $state['type'] ?? null,
                'latitude' => $state['latitude'] ?? null,
                'longitude' => $state['longitude'] ?? null,
                'synced_at' => $syncedAt,
            ],
        );
    }


    // Upsert Cities
    //
    // Writes every city under one state in chunked query-builder upserts
    // rather than per-row Eloquent calls, given the scale involved (see
    // CITY_CHUNK_SIZE).
    //
    // @ai
    //		state_id/timezone_id are only ever set to a real value when
    //		their own dataset is also enabled — states/timezones tables can
    //		independently not exist at all (see the states/timezones
    //		migrations), so writing a value that references a nonexistent
    //		table's row would leave a dangling reference. Left null
    //		otherwise, which is what lets City::state()/timezone() (plain
    //		belongsTo) stay safe via Eloquent's own null-foreign-key
    //		short-circuit rather than needing special handling themselves.
    //
    // @see isValidPhpTimezone()
    //
    // @param $country array - The parent country record from the source release
    // @param $state array - The parent state record from the source release
    // @param $cities array - The state's city records from the source release
    // @param $syncedAt \DateTimeInterface - Timestamp to stamp these rows with
    //
    // @return void
    //
    protected function upsertCities(array $country, array $state, array $cities, DateTimeInterface $syncedAt): void
    {
        if (empty($cities)) {
            return;
        }

        $importStates = config('locale.datasets.states');
        $importTimezones = config('locale.datasets.timezones');

        $stateId = $importStates
            ? ($state['iso3166_2'] ?? $country['iso2'].'-'.$state['iso2'])
            : null;

        $rows = [];

        foreach ($cities as $city) {
            $rows[] = [
                'id' => $city['id'],
                'country_id' => $country['iso2'],
                'state_id' => $stateId,
                'timezone_id' => $importTimezones && isset($city['timezone']) && $this->isValidPhpTimezone($city['timezone'])
                    ? $city['timezone']
                    : null,
                'name' => $city['name'],
                'latitude' => $city['latitude'] ?? null,
                'longitude' => $city['longitude'] ?? null,
                'synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];
        }

        $uniqueBy = ['id'];
        $update = ['country_id', 'state_id', 'timezone_id', 'name', 'latitude', 'longitude', 'synced_at', 'updated_at'];

        foreach (array_chunk($rows, self::CITY_CHUNK_SIZE) as $chunk) {
            DB::table(config('locale.table_names.cities'))->upsert($chunk, $uniqueBy, $update);
        }

        $this->citiesImported += count($rows);
    }


    // Upsert Timezone
    //
    // Writes one timezone row and its country_timezone pivot row — but
    // only when the zone name is one this PHP installation's own tzdata
    // recognizes (see @ai note and isValidPhpTimezone()).
    //
    // @ai
    //		Skipping rather than importing an invalid zone name is
    //		deliberate: importing it anyway would mean a value that throws
    //		the moment application code does `new DateTimeZone($zone)`. See
    //		this package's README for the known ~2.6% drift between the
    //		source and PHP's tzdata.
    //
    // @see isValidPhpTimezone()
    //
    // @param $country array - The parent country record from the source release
    // @param $timezone array - One timezone record from the source release
    // @param $syncedAt \DateTimeInterface - Timestamp to stamp this row with
    //
    // @return void
    //
    protected function upsertTimezone(array $country, array $timezone, DateTimeInterface $syncedAt): void
    {
        $zoneName = $timezone['zoneName'];

        if (! $this->isValidPhpTimezone($zoneName)) {
            return;
        }

        Timezone::query()->updateOrCreate(
            ['id' => $zoneName],
            [
                'abbreviation' => $timezone['abbreviation'] ?? null,
                'gmt_offset' => $timezone['gmtOffset'] ?? null,
                'gmt_offset_name' => $timezone['gmtOffsetName'] ?? null,
                'name' => $timezone['tzName'] ?? null,
                'synced_at' => $syncedAt,
            ],
        );

        DB::table(config('locale.table_names.country_timezone'))->upsert(
            [['country_id' => $country['iso2'], 'timezone_id' => $zoneName]],
            ['country_id', 'timezone_id'],
        );
    }


    // Is Valid Php Timezone
    //
    // Checks a zone name against this PHP installation's own
    // DateTimeZone::listIdentifiers(), caching the result for the rest of
    // this run either way so repeated cities/countries referencing the
    // same zone don't re-check it.
    //
    // @param $zoneName string - The IANA zone name to validate [Ex: America/Chicago]
    //
    // @return bool
    //
    protected function isValidPhpTimezone(string $zoneName): bool
    {
        if (isset($this->validatedZones[$zoneName])) {
            return true;
        }

        if (in_array($zoneName, DateTimeZone::listIdentifiers(), strict: true)) {
            $this->validatedZones[$zoneName] = true;

            return true;
        }

        if (! in_array($zoneName, $this->invalidZones, strict: true)) {
            $this->invalidZones[] = $zoneName;
        }

        return false;
    }
}

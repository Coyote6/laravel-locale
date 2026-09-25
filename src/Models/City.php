<?php

namespace Coyote6\LaravelLocale\Models;

use Closure;
use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Coyote6\LaravelLocale\Concerns\HasCountryColumnOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No Concerns\HasAbbreviation / HasAbbr here — see config/locale.php's
// 'abbreviations' block: cities have no natural code/iso field for an
// abbreviation to mean anything.
//
// @ai
//		Unlike every other model in this package, $keyType/$incrementing
//		aren't overridden to 'string'/false — cities have no natural code
//		either, so this table's primary key is a plain integer: the
//		source's own stable numeric id, not Laravel's auto-increment (the
//		importer sets it explicitly), but still numeric like Eloquent's
//		default expects. See GeoDataImporter::upsertCities().
//
// @ai
//		getAsOptions() (from Concerns\HasCountryColumnOptions ->
//		Concerns\BuildsFilteredOptions -> coyote6/laravel-base's
//		GetAsOptions) is inherited here same as every other model, but this
//		table can run to ~153,000 rows — pass $limit, or prefer
//		getAsOptionsWhereCountryIs() / getAsOptionsWhereStateIs() /
//		getAsOptionsWhereStateAndCountryAre() below, rather than plucking
//		every city.
class City extends Model
{
    use BuildsEmptyRelations, HasCountryColumnOptions;

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'synced_at' => 'datetime',
    ];


    // Get Table
    //
    // Overrides Eloquent's default table-name guess with the configured
    // name, so consuming apps can rename the table without extending this
    // model.
    //
    // @return string
    //
    public function getTable(): string
    {
        return config('locale.table_names.cities');
    }


    //
    // Relationships
    //


    // Country
    //
    // @ai
    //		Falls back to emptyBelongsTo() when config('locale.datasets.countries')
    //		is off — country_id is always populated regardless (see the
    //		cities migration's @ai note), so this can't rely on the usual
    //		null-FK short-circuit; a real query against a nonexistent
    //		countries table would still throw.
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    //
    public function country(): BelongsTo
    {
        if (! config('locale.datasets.countries')) {
            return $this->emptyBelongsTo('country_id', 'country');
        }

        return $this->belongsTo(Country::class);
    }


    // State
    //
    // @ai
    //		No hard FK constraint backs this in the migration — states is an
    //		independently toggleable dataset (config('locale.datasets.states')),
    //		so this may be populated even when the states table isn't. See
    //		database/migrations/..._create_cities_table.php.
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    //
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }


    // Timezone
    //
    // @ai
    //		Same reasoning as state() above — timezones is independently
    //		toggleable, and this column only exists at all when
    //		config('locale.datasets.timezones') is enabled.
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    //
    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }


    //
    // Options
    //


    // Get As Options Where State Is
    //
    // getAsOptions() scoped to one state's cities. $state may be a State
    // model (its key -- the ISO 3166-2 id, e.g. "US-CA" -- is used), that id
    // as a string (this column's exact value, so it filters directly with no
    // lookup), or a closure that receives the query builder and applies its
    // own filter.
    //
    // @see Concerns\BuildsFilteredOptions::optionFilter()
    //
    // @param $state \Coyote6\LaravelLocale\Models\State|string|\Closure(\Illuminate\Database\Eloquent\Builder): void - State model, its ISO 3166-2 id, or a custom filter closure [Ex: 'US-CA']
    // @param $key string - Attribute to key each option by [Ex: id]
    // @param $field string - Attribute to use as each option's label [Ex: name]
    // @param $limit int - Maximum options to return, or 0 for all [Ex: 25]
    // @param $page int - 1-based page to return when $limit is set [Ex: 2]
    // @param $modifyQuery ?\Closure(\Illuminate\Database\Eloquent\Builder): void - The caller's own query adjustments, run after the state filter; owns ordering when given
    //
    // @return array<array-key, string>
    //
    public static function getAsOptionsWhereStateIs(State|string|Closure $state, string $key = 'id', string $field = 'name', int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array
    {
        return static::getAsOptionsFiltered(
            static::optionFilter($state, 'state_id'),
            $key,
            $field,
            $limit,
            $page,
            $modifyQuery,
        );
    }


    // Get As Options Where State And Country Are
    //
    // getAsOptions() scoped to one state's cities AND that state's country --
    // $state and $country each accept the same Model|string|Closure forms as
    // getAsOptionsWhereStateIs() / Concerns\HasCountryColumnOptions::getAsOptionsWhereCountryIs()
    // above, and both filters are applied together.
    //
    // @see Concerns\BuildsFilteredOptions::optionFilter()
    //
    // @param $state \Coyote6\LaravelLocale\Models\State|string|\Closure(\Illuminate\Database\Eloquent\Builder): void - State model, its ISO 3166-2 id, or a custom filter closure [Ex: 'US-CA']
    // @param $country \Coyote6\LaravelLocale\Models\Country|string|\Closure(\Illuminate\Database\Eloquent\Builder): void - Country model, its alpha-2 id, or a custom filter closure [Ex: 'US']
    // @param $key string - Attribute to key each option by [Ex: id]
    // @param $field string - Attribute to use as each option's label [Ex: name]
    // @param $limit int - Maximum options to return, or 0 for all [Ex: 25]
    // @param $page int - 1-based page to return when $limit is set [Ex: 2]
    // @param $modifyQuery ?\Closure(\Illuminate\Database\Eloquent\Builder): void - The caller's own query adjustments, run after both filters; owns ordering when given
    //
    // @return array<array-key, string>
    //
    public static function getAsOptionsWhereStateAndCountryAre(State|string|Closure $state, Country|string|Closure $country, string $key = 'id', string $field = 'name', int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array
    {
        $stateFilter = static::optionFilter($state, 'state_id');
        $countryFilter = static::optionFilter($country, 'country_id');

        return static::getAsOptionsFiltered(
            function ($query) use ($stateFilter, $countryFilter): void {
                $stateFilter($query);
                $countryFilter($query);
            },
            $key,
            $field,
            $limit,
            $page,
            $modifyQuery,
        );
    }
}

<?php

namespace Coyote6\LaravelLocale\Models;

use Closure;
use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Coyote6\LaravelLocale\Concerns\BuildsFilteredOptions;
use Coyote6\LaravelLocale\Concerns\HasAbbr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Timezone extends Model
{
    use BuildsEmptyRelations, BuildsFilteredOptions, HasAbbr;


    // Model
    protected $keyType = 'string';

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
        return config('locale.table_names.timezones');
    }


    // Abbreviation Config Key
    //
    // Tells Concerns\HasAbbr which config('locale.abbreviations.*') entry
    // this model reads its abbr accessor from.
    //
    // @ai
    //		This model only uses HasAbbr, not HasAbbreviation — its
    //		'abbreviation' column already has that exact name, so
    //		getAbbreviationAttribute() would just be a pointless pass-through
    //		of the real column. See Concerns\HasAbbreviation's own @ai note
    //		for why that would also be a genuine infinite-recursion risk if
    //		implemented naively, not just redundant.
    //
    // @return string
    //
    protected function abbreviationConfigKey(): string
    {
        return 'timezones';
    }


    //
    // Relationships
    //


    // Countries
    //
    // @ai
    //		Falls back to emptyBelongsToMany() when config('locale.datasets.countries')
    //		is off — no override needed for the fake-pivot table, since
    //		Timezone's own table isn't a candidate collision the way
    //		Country::timezones() is (see Concerns\BuildsEmptyRelations).
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
    //
    public function countries(): BelongsToMany
    {
        if (! config('locale.datasets.countries')) {
            return $this->emptyBelongsToMany();
        }

        return $this->belongsToMany(Country::class, config('locale.table_names.country_timezone'));
    }


    //
    // Options
    //


    // Get As Options Where Country Is
    //
    // getAsOptions() scoped to the timezones linked to one country. Filters
    // the country_timezone pivot's own country_id column directly, rather
    // than going through the countries() relationship -- that column is
    // always populated whenever this table exists at all (see
    // GeoDataImporter::upsertTimezone()), so this resolves correctly even
    // when config('locale.datasets.countries') is off and the countries
    // table doesn't exist.
    //
    // $country may be a Country model (its key -- the alpha-2 code -- is
    // used), that code as a string (the pivot's exact country_id value, so
    // it filters directly with no lookup), or a closure that receives the
    // query builder and applies its own filter (e.g. to match a non-key
    // identifier via the countries() relationship instead).
    //
    // @param $country \Coyote6\LaravelLocale\Models\Country|string|\Closure(\Illuminate\Database\Eloquent\Builder): void - Country model, its alpha-2 id, or a custom filter closure [Ex: 'US']
    // @param $key string - Attribute to key each option by [Ex: id]
    // @param $field string - Attribute to use as each option's label [Ex: name]
    // @param $limit int - Maximum options to return, or 0 for all [Ex: 25]
    // @param $page int - 1-based page to return when $limit is set [Ex: 2]
    // @param $modifyQuery ?\Closure(\Illuminate\Database\Eloquent\Builder): void - The caller's own query adjustments, run after the country filter; owns ordering when given
    //
    // @return array<array-key, string>
    //
    public static function getAsOptionsWhereCountryIs(Country|string|Closure $country, string $key = 'id', string $field = 'name', int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array
    {
        if ($country instanceof Closure) {
            $filter = $country;
        } else {
            $countryId = $country instanceof Country ? $country->getKey() : $country;

            $filter = function (Builder $query) use ($countryId): void {
                $query->whereIn(
                    $query->getModel()->getKeyName(),
                    fn (QueryBuilder $pivot) => $pivot
                        ->select('timezone_id')
                        ->from(config('locale.table_names.country_timezone'))
                        ->where('country_id', $countryId),
                );
            };
        }

        return static::getAsOptionsFiltered($filter, $key, $field, $limit, $page, $modifyQuery);
    }
}

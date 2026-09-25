<?php

namespace Coyote6\LaravelLocale\Concerns;

use Closure;
use Coyote6\LaravelLocale\Models\Country;

// getAsOptionsWhereCountryIs() for models with a real, always-populated
// country_id column (Models\State, Models\City). Filters that column
// directly rather than going through the country() relationship, so it
// resolves correctly whether or not the countries dataset is enabled --
// country_id is already known from the source's own record and needs no
// countries-table lookup (see GeoDataImporter::upsertState() /
// upsertCities(), and Models\State::country() / Models\City::country() for
// how the relationship itself stays safe when countries is off).
trait HasCountryColumnOptions
{
    use BuildsFilteredOptions;


    // Get As Options Where Country Is
    //
    // getAsOptions() scoped to one country's rows. $country may be a Country
    // model (its key -- the ISO 3166-1 alpha-2 code -- is used), the alpha-2
    // code itself as a string (this column's exact value, so it filters
    // directly with no lookup), or a closure that receives the query builder
    // and applies its own filter (e.g. to match a non-key identifier like
    // iso3 or name via the country() relationship instead).
    //
    // @see Concerns\BuildsFilteredOptions::optionFilter()
    //
    // @param $country \Coyote6\LaravelLocale\Models\Country|string|\Closure(\Illuminate\Database\Eloquent\Builder): void - Country model, its alpha-2 id, or a custom filter closure [Ex: 'US']
    // @param $key string - Attribute to key each option by [Ex: id, code]
    // @param $field string - Attribute to use as each option's label [Ex: name]
    // @param $limit int - Maximum options to return, or 0 for all [Ex: 25]
    // @param $page int - 1-based page to return when $limit is set [Ex: 2]
    // @param $modifyQuery ?\Closure(\Illuminate\Database\Eloquent\Builder): void - The caller's own query adjustments, run after the country filter; owns ordering when given
    //
    // @return array<array-key, string>
    //
    public static function getAsOptionsWhereCountryIs(Country|string|Closure $country, string $key = 'id', string $field = 'name', int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array
    {
        return static::getAsOptionsFiltered(
            static::optionFilter($country, 'country_id'),
            $key,
            $field,
            $limit,
            $page,
            $modifyQuery,
        );
    }
}

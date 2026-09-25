<?php

namespace Coyote6\LaravelLocale\Concerns;

use Closure;
use Coyote6\LaravelBase\Traits\Models\GetAsOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Composition engine behind this package's getAsOptionsWhere*Is() helpers
// (see Models\State::getAsOptionsWhereCountryIs(), Models\City's three
// variants, Models\Timezone::getAsOptionsWhereCountryIs(),
// Models\Subregion::getAsOptionsWhereRegionIs()). Re-exports
// coyote6/laravel-base's GetAsOptions so any model using this trait already
// has the base getAsOptions() method too.
trait BuildsFilteredOptions
{
    use GetAsOptions;


    // Get As Options Filtered
    //
    // Delegates to getAsOptions() with a composed closure: $applyFilter (this
    // package's relationship filter, from optionFilter() below) always runs
    // first, then the caller's own $modifyQuery if given, otherwise the
    // default $field-ascending sort -- matching GetAsOptions' rule that an
    // explicit $modifyQuery owns ordering.
    //
    // @param $applyFilter \Closure(\Illuminate\Database\Eloquent\Builder): void - Adds this helper's relationship filter to the query
    // @param $key string - Attribute to key each option by [Ex: id, code]
    // @param $field string - Attribute to use as each option's label [Ex: name]
    // @param $limit int - Maximum options to return, or 0 for all [Ex: 25]
    // @param $page int - 1-based page to return when $limit is set [Ex: 2]
    // @param $modifyQuery ?\Closure(\Illuminate\Database\Eloquent\Builder): void - The caller's own query adjustments, run after $applyFilter; owns ordering when given
    //
    // @return array<array-key, string>
    //
    protected static function getAsOptionsFiltered(Closure $applyFilter, string $key = 'id', string $field = 'name', int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array
    {
        return static::getAsOptions($key, $field, $limit, $page, function (Builder $query) use ($applyFilter, $field, $modifyQuery): void {
            $applyFilter($query);

            if ($modifyQuery) {
                $modifyQuery($query);
            } else {
                $query->orderBy($field, 'asc');
            }
        });
    }


    // Option Filter
    //
    // Normalizes a getAsOptionsWhere*Is() relation argument into a filter
    // closure for getAsOptionsFiltered(). A Closure is trusted as-is and
    // returned unchanged -- it IS the filter, and owns matching on whatever
    // column/relationship the caller wants (e.g. an iso3 code instead of the
    // primary key). A Model or a bare key string both resolve to a plain
    // equality check on $column: a Model via ->getKey(), a string taken as
    // the column's value directly (no lookup -- see the calling method's own
    // docblock for why a string is always safe to use this way here).
    //
    // @param $target \Illuminate\Database\Eloquent\Model|string|\Closure(\Illuminate\Database\Eloquent\Builder): void - The relation argument to normalize [Ex: $country, 'US', fn ($q) => $q->whereHas(...)]
    // @param $column string - The local column to filter on when $target isn't already a Closure [Ex: country_id]
    //
    // @return \Closure(\Illuminate\Database\Eloquent\Builder): void
    //
    protected static function optionFilter(Model|string|Closure $target, string $column): Closure
    {
        if ($target instanceof Closure) {
            return $target;
        }

        $value = $target instanceof Model ? $target->getKey() : $target;

        return fn (Builder $query) => $query->where($column, $value);
    }
}

<?php

namespace Coyote6\LaravelLocale\Models;

use Closure;
use Coyote6\LaravelLocale\Concerns\BuildsFilteredOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No Concerns\HasAbbreviation / HasAbbr here — see config/locale.php's
// 'abbreviations' block: subregions have no natural code/iso field for an
// abbreviation to mean anything.
class Subregion extends Model
{
    use BuildsFilteredOptions;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'translations' => 'array',
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
        return config('locale.table_names.subregions');
    }


    //
    // Relationships
    //


    // Region
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    //
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }


    //
    // Options
    //


    // Get As Options Where Region Is
    //
    // getAsOptions() scoped to one region's subregions. $region may be a
    // Region model (its key -- a slug of the region's name -- is used), that
    // slug as a string (this column's exact value, so it filters directly
    // with no lookup), or a closure that receives the query builder and
    // applies its own filter.
    //
    // @ai
    //		region_id is only populated when config('locale.datasets.regions')
    //		was enabled at import time (see
    //		GeoDataImporter::importRegionsAndSubregions()) -- if it was off,
    //		every subregion's region_id is null and this returns an empty
    //		list regardless of $region.
    //
    // @see Concerns\BuildsFilteredOptions::optionFilter()
    //
    // @param $region \Coyote6\LaravelLocale\Models\Region|string|\Closure(\Illuminate\Database\Eloquent\Builder): void - Region model, its slug id, or a custom filter closure [Ex: 'americas']
    // @param $key string - Attribute to key each option by [Ex: id]
    // @param $field string - Attribute to use as each option's label [Ex: name]
    // @param $limit int - Maximum options to return, or 0 for all [Ex: 25]
    // @param $page int - 1-based page to return when $limit is set [Ex: 2]
    // @param $modifyQuery ?\Closure(\Illuminate\Database\Eloquent\Builder): void - The caller's own query adjustments, run after the region filter; owns ordering when given
    //
    // @return array<array-key, string>
    //
    public static function getAsOptionsWhereRegionIs(Region|string|Closure $region, string $key = 'id', string $field = 'name', int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array
    {
        return static::getAsOptionsFiltered(
            static::optionFilter($region, 'region_id'),
            $key,
            $field,
            $limit,
            $page,
            $modifyQuery,
        );
    }
}

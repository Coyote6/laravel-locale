<?php

namespace Coyote6\LaravelLocale\Models;

use Coyote6\LaravelBase\Traits\Models\GetAsOptions;
use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// No Concerns\HasAbbreviation / HasAbbr here — see config/locale.php's
// 'abbreviations' block: regions have no natural code/iso field for an
// abbreviation to mean anything.
class Region extends Model
{
    use BuildsEmptyRelations, GetAsOptions;

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
        return config('locale.table_names.regions');
    }


    //
    // Relationships
    //


    // Subregions
    //
    // @ai
    //		Falls back to emptyHasMany() when config('locale.datasets.subregions')
    //		is off — subregions and regions are independently toggleable
    //		(a Region instance can exist while the subregions table doesn't).
    //		See Concerns\BuildsEmptyRelations.
    //
    // @return \Illuminate\Database\Eloquent\Relations\HasMany
    //
    public function subregions(): HasMany
    {
        if (! config('locale.datasets.subregions')) {
            return $this->emptyHasMany();
        }

        return $this->hasMany(Subregion::class);
    }
}

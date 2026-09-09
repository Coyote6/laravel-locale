<?php

namespace Coyote6\LaravelLocale\Models;

use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Coyote6\LaravelLocale\Concerns\HasAbbr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Timezone extends Model
{
    use BuildsEmptyRelations, HasAbbr;


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
}

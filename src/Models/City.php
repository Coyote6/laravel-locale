<?php

namespace Coyote6\LaravelLocale\Models;

use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
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
class City extends Model
{
    use BuildsEmptyRelations;

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
}

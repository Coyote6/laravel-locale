<?php

namespace Coyote6\LaravelLocale\Models;

use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Coyote6\LaravelLocale\Concerns\HasAbbr;
use Coyote6\LaravelLocale\Concerns\HasAbbreviation;
use Coyote6\LaravelLocale\Concerns\HasCountryColumnOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class State extends Model
{
    use BuildsEmptyRelations, HasAbbr, HasAbbreviation, HasCountryColumnOptions;


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
        return config('locale.table_names.states');
    }


    // Abbreviation Config Key
    //
    // Tells Concerns\HasAbbreviation / HasAbbr which config('locale.abbreviations.*')
    // entry this model reads its abbreviation/abbr accessors from.
    //
    // @return string
    //
    protected function abbreviationConfigKey(): string
    {
        return 'states';
    }


    //
    // Relationships
    //


    // Country
    //
    // @ai
    //		Falls back to emptyBelongsTo() when config('locale.datasets.countries')
    //		is off — country_id is always populated regardless (see the
    //		states migration's @ai note), so this can't rely on the usual
    //		null-FK short-circuit the way State::country() on other models
    //		does; a real query against a nonexistent countries table would
    //		still throw. Left on Eloquent's default FK/owner-key guessing
    //		(country_id / countries.id) in the enabled branch rather than
    //		passing them explicitly — both models keep their primary key
    //		named 'id' specifically so this default resolves correctly with
    //		no override needed.
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
}

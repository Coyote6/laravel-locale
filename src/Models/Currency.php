<?php

namespace Coyote6\LaravelLocale\Models;

use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Coyote6\LaravelLocale\Concerns\HasAbbr;
use Coyote6\LaravelLocale\Concerns\HasAbbreviation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use BuildsEmptyRelations, HasAbbr, HasAbbreviation;


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
        return config('locale.table_names.currencies');
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
        return 'currencies';
    }


    //
    // Relationships
    //


    // Countries
    //
    // Every country that uses this currency — e.g. Currency::find('EUR')->countries()
    // returns every Eurozone country, since currency_id is deduplicated
    // (see GeoDataImporter::upsertCurrency()), not repeated per country.
    //
    // @ai
    //		Falls back to emptyHasMany() when config('locale.datasets.countries')
    //		is off — a Currency row can exist independently of Country (see
    //		GeoDataImporter::upsertCountry()), so the countries table this
    //		queries may not exist at all.
    //
    // @return \Illuminate\Database\Eloquent\Relations\HasMany
    //
    public function countries(): HasMany
    {
        if (! config('locale.datasets.countries')) {
            return $this->emptyHasMany();
        }

        return $this->hasMany(Country::class);
    }
}

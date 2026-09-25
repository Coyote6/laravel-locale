<?php

namespace Coyote6\LaravelLocale\Models;

use Closure;
use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Coyote6\LaravelLocale\Concerns\HasAbbr;
use Coyote6\LaravelLocale\Concerns\HasAbbreviation;
use Coyote6\LaravelLocale\Concerns\HasCountryColumnOptions;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class State extends Model
{
    use BuildsEmptyRelations, HasAbbr, HasAbbreviation, HasCountryColumnOptions {
        HasCountryColumnOptions::getAsOptions as protected getAsBaseOptions;
    }


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


    //
    // Options
    //


    // Get As Options
    //
    // Same as GetAsOptions::getAsOptions(), except the default $field
    // combines the country code and state name ("US - California") instead
    // of the bare name, so a list mixing states from more than one country
    // stays legible. Pass an explicit $field to opt back into a plain
    // column. No join needed -- country_id is already denormalized onto
    // this table (see country()'s own @ai note above).
    //
    // @ai
    //		States number in the low thousands globally, not hundreds of
    //		thousands like City -- an unscoped call here is far cheaper than
    //		on City. Still: prefer getAsOptionsWhereCountryIs() when only
    //		one country's states are needed, and pass $limit for a
    //		paginated picker either way.
    //
    // @param $key string - Attribute to key each option by [Ex: id]
    // @param $field string|\Illuminate\Contracts\Database\Query\Expression|\Closure(\Illuminate\Database\Eloquent\Builder): void|null - Column, DB::raw() expression, or Closure -- null uses the "country - name" default [Ex: 'name']
    // @param $limit int - Maximum options to return, or 0 for all [Ex: 25]
    // @param $page int - 1-based page to return when $limit is set [Ex: 2]
    // @param $modifyQuery ?\Closure(\Illuminate\Database\Eloquent\Builder): void - The caller's own query adjustments; owns ordering when given, unless $field is also left null (default country/name ordering applies)
    //
    // @return array<array-key, string>
    //
    public static function getAsOptions(string $key = 'id', string|Expression|Closure|null $field = null, int $limit = 0, int $page = 1, ?Closure $modifyQuery = null): array
    {
        if ($field === null) {
            $field = DB::raw(static::concatSql(['country_id', "' - '", 'name']).' AS label');
            $modifyQuery ??= fn ($query) => $query->orderBy('country_id')->orderBy('name');
        }

        return static::getAsBaseOptions($key, $field, $limit, $page, $modifyQuery);
    }
}

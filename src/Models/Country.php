<?php

namespace Coyote6\LaravelLocale\Models;

use Coyote6\LaravelLocale\Concerns\BuildsEmptyRelations;
use Coyote6\LaravelLocale\Concerns\HasAbbr;
use Coyote6\LaravelLocale\Concerns\HasAbbreviation;
use Coyote6\LaravelLocale\DataTransferObjects\Language;
use Coyote6\LaravelLocale\Facades\Locale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Country extends Model
{
    use BuildsEmptyRelations, HasAbbr, HasAbbreviation;


    // Model
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
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
        return config('locale.table_names.countries');
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
        return 'countries';
    }


    //
    // Relationships
    //


    // States
    //
    // @ai
    //		Falls back to emptyHasMany() when config('locale.datasets.states')
    //		is off — the states table may not exist at all in that case
    //		(see the states migration), and a real HasMany querying it would
    //		throw. See Concerns\BuildsEmptyRelations for why this is safe
    //		under ->get(), with(), whereHas(), etc., not just a plain call.
    //
    // @return \Illuminate\Database\Eloquent\Relations\HasMany
    //
    public function states(): HasMany
    {
        if (! config('locale.datasets.states')) {
            return $this->emptyHasMany();
        }

        return $this->hasMany(State::class);
    }


    // Timezones
    //
    // @ai
    //		Falls back to emptyBelongsToMany() when config('locale.datasets.timezones')
    //		is off, for the same reason as states() above. Its fake-pivot
    //		table comes from config('locale.empty_relation_table')
    //		('states' by default) — see Concerns\BuildsEmptyRelations for
    //		why that config can't just default to 'countries' (this is its
    //		one real caller today, called ON a Country instance) and how it
    //		falls back automatically if the configured table is ever
    //		changed to something that collides with the caller's own table.
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
    //
    public function timezones(): BelongsToMany
    {
        if (! config('locale.datasets.timezones')) {
            return $this->emptyBelongsToMany();
        }

        return $this->belongsToMany(Timezone::class, config('locale.table_names.country_timezone'));
    }


    // Currency
    //
    // @ai
    //		Singular, not plural — unlike languages, the source only ever
    //		gives one currency per country (verified: 0 of 250 countries
    //		have more than one), so there's no "primary vs. other" ambiguity
    //		to defer the way there was for languages. Real belongsTo, not a
    //		DTO-wrapping method — currencies are deduplicated into a real
    //		table (see Models\Currency, GeoDataImporter::upsertCurrency()),
    //		unlike languages where no stored table exists at all.
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    //
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }


    // Languages
    //
    // Every language this country is linked to, resolved live through the
    // Locale facade — not a real Eloquent relationship, since there's no
    // Language Eloquent model to relate to (language data is never stored,
    // see LocaleManager). Reads the country_language pivot for this
    // country's language codes, then resolves each through Symfony Intl.
    //
    // @ai
    //		Checks config('locale.datasets.country_languages') itself,
    //		unlike the real relationships above — this is already a plain
    //		method returning array, not a typed Relation with an ->get()
    //		a caller could invoke later, so there's no type contract to
    //		preserve and no need for the emptyHasMany()-style trick; a
    //		direct early return is simplest. The country_language table may
    //		not exist at all when this dataset is off (see its migration).
    //
    //		No language() (singular/primary) method exists yet — the only
    //		available source (mledoze/countries, see config/locale.php's
    //		'source' block) doesn't mark an official/primary language, and
    //		"first listed = primary" would be an arbitrary, unverified
    //		heuristic. Revisit in a future release if a source that
    //		actually marks official status turns up.
    //
    // @return array<int, \Coyote6\LaravelLocale\DataTransferObjects\Language>
    //
    public function languages(): array
    {
        if (! config('locale.datasets.country_languages')) {
            return [];
        }

        return DB::table(config('locale.table_names.country_language'))
            ->where('country_id', $this->getKey())
            ->pluck('language_code')
            ->map(fn (string $code): ?Language => Locale::language($code))
            ->filter()
            ->values()
            ->all();
    }
}

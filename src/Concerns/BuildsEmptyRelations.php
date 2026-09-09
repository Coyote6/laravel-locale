<?php

namespace Coyote6\LaravelLocale\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Builds a real BelongsTo/HasMany/BelongsToMany that can never match a row,
// for a relationship whose target table belongs to a disabled dataset and
// may not exist at all. A query against a genuinely nonexistent table always
// throws — no WHERE clause can prevent that, the table name has to resolve
// before the database will even parse the query — so the only way to
// "return empty" for real is to never touch that table at all.
//
// @ai
//		Confirmed empirically (see this package's chat history/commit notes)
//		that scoping the query to the model's own always-existing table with
//		an always-false predicate works correctly through ->get(), dynamic
//		property access, with() eager loading, whereHas(), and
//		whereDoesntHave() — every generated SQL statement only ever
//		references always-on tables. getRelated() reports the wrong model
//		class in this fake case (whatever table the fake query targets, not
//		the real related model), but since the query can never return a row,
//		nothing is ever hydrated as that wrong class — it's inert.
trait BuildsEmptyRelations
{


    // Empty Relation Datasets
    //
    // This package's own toggleable entity tables, in the order
    // findEmptyRelationFallbackTable() tries them. Deliberately excludes
    // 'countries' (see config/locale.php's 'empty_relation_table' block)
    // and the country_timezone/country_language pivots (see @ai note on
    // findEmptyRelationFallbackTable() for why neither can serve as a fake
    // pivot table at all).
    protected const EMPTY_RELATION_DATASETS = ['currencies', 'states', 'timezones', 'regions', 'subregions', 'cities'];


    // Empty Belongs To
    //
    // For a belongsTo relationship whose target table may not exist —
    // unlike emptyHasMany()/emptyBelongsToMany(), doesn't need a fallback
    // table at all: the local foreign key column stays populated with its
    // real value (see e.g. Models\State::country()'s @ai note for why), so
    // this can't rely on Eloquent's null-FK short-circuit the way an
    // untouched belongsTo does. Scoping the query to $this model's own
    // always-existing table with an always-false predicate sidesteps that
    // the same way the other two methods here do.
    //
    // @param $foreignKey string - The local column that would hold the related model's key [Ex: country_id]
    // @param $relation string - The relationship method's own name, matching what belongsTo() would infer automatically [Ex: country]
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    //
    protected function emptyBelongsTo(string $foreignKey, string $relation): BelongsTo
    {
        return new BelongsTo(
            $this->newQuery()->whereRaw('1 = 0'),
            $this,
            $foreignKey,
            $this->getKeyName(),
            $relation,
        );
    }


    // Empty Has Many
    //
    // @return \Illuminate\Database\Eloquent\Relations\HasMany
    //
    protected function emptyHasMany(): HasMany
    {
        return new HasMany(
            $this->newQuery()->whereRaw('1 = 0'),
            $this,
            $this->getKeyName(),
            $this->getKeyName(),
        );
    }


    // Empty Belongs To Many
    //
    // @ai
    //		Defaults to resolveEmptyRelationTable() rather than a fixed
    //		table — see that method and config('locale.empty_relation_table')
    //		for why a single hardcoded default can't cover every caller
    //		(the self-join problem this whole trick exists to avoid).
    //
    // @param $fakePivotTable string|null - An always-existing table, DIFFERENT from $this model's own table [Ex: states]
    //
    // @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
    //
    protected function emptyBelongsToMany(?string $fakePivotTable = null): BelongsToMany
    {
        $fakePivotTable ??= $this->resolveEmptyRelationTable();

        return new BelongsToMany(
            $this->newQuery()->whereRaw('1 = 0'),
            $this,
            $fakePivotTable,
            $this->getKeyName(),
            $this->getKeyName(),
            $this->getKeyName(),
            $this->getKeyName(),
        );
    }


    // Resolve Empty Relation Table
    //
    // Picks the table emptyBelongsToMany() joins against when no explicit
    // override is passed: config('locale.empty_relation_table') itself, as
    // long as it isn't $this model's own table (self-join) and — if it
    // happens to be one of this package's own toggleable entity tables —
    // its dataset is actually enabled. Anything outside those two checks
    // (an app's own table, 'migrations', etc.) is trusted as-is, since
    // there's no config('locale.datasets.*') entry to verify it against.
    // findEmptyRelationFallbackTable() takes over when either check fails.
    //
    // @return string
    //
    protected function resolveEmptyRelationTable(): string
    {
        $configured = config('locale.empty_relation_table');

        if ($this->emptyRelationTableIsUsable($configured)) {
            return $configured;
        }

        return $this->findEmptyRelationFallbackTable();
    }


    // Find Empty Relation Fallback Table
    //
    // Walks EMPTY_RELATION_DATASETS in order for the first table that's
    // both enabled and different from $this model's own table — the same
    // two checks resolveEmptyRelationTable() applies to the configured
    // table, reused here via emptyRelationTableIsUsable().
    //
    // @ai
    //		'countries' isn't a candidate here on purpose — it's the one
    //		caller (Country::timezones()) this whole fallback path exists
    //		for, so it can never be a valid answer for that call anyway.
    //		'country_timezone'/'country_language' aren't candidates either
    //		— both are pivot tables keyed on a composite of two foreign
    //		columns, not an 'id' column, and the BelongsToMany this feeds
    //		joins on '<table>.id' regardless of the always-false predicate;
    //		a missing column fails to parse the same way a missing table
    //		does. 'migrations' is the last resort if every dataset here is
    //		disabled (or matches $this model's own table) — the one table
    //		virtually guaranteed to exist in any real Laravel app.
    //
    // @return string
    //
    protected function findEmptyRelationFallbackTable(): string
    {
        foreach (self::EMPTY_RELATION_DATASETS as $dataset) {
            if (! config("locale.datasets.{$dataset}")) {
                continue;
            }

            $table = config("locale.table_names.{$dataset}");

            if ($this->emptyRelationTableIsUsable($table)) {
                return $table;
            }
        }

        return 'migrations';
    }


    // Empty Relation Table Is Usable
    //
    // A table is usable as a fake pivot when it isn't $this model's own
    // table (self-join) and, if it maps to one of this package's own
    // toggleable entity tables, that dataset is actually enabled. A table
    // this package doesn't recognize at all (an app's own table,
    // 'migrations') has no dataset to check, so it's trusted by name alone.
    //
    // @param $table string - The candidate fake-pivot table name [Ex: states]
    //
    // @return bool
    //
    protected function emptyRelationTableIsUsable(string $table): bool
    {
        if ($table === $this->getTable()) {
            return false;
        }

        $dataset = array_search($table, config('locale.table_names'), strict: true);

        if (! in_array($dataset, self::EMPTY_RELATION_DATASETS, strict: true)) {
            return true;
        }

        return (bool) config("locale.datasets.{$dataset}");
    }
}

<?php

namespace Coyote6\LaravelLocale\Concerns;

// Defines $model->abbreviation for any model that pairs this trait with a
// abbreviationConfigKey() method (see Models\Country, Models\State).
trait HasAbbreviation
{


    // Get Abbreviation Attribute
    //
    // Eloquent accessor for $model->abbreviation. Reads whichever field
    // config('locale.abbreviations.*') names for this model, via
    // getAttributeFromArray() rather than $this->{$field}.
    //
    // @ai
    //		Reading through the magic getter ($this->{$field}) would be wrong
    //		whenever $field resolves to 'abbreviation' itself — that would
    //		re-trigger this same accessor and recurse infinitely.
    //		getAttributeFromArray() reads the raw stored value directly,
    //		sidestepping the accessor chain entirely, so this trait is safe
    //		to use even on a model whose configured field happens to share
    //		this accessor's name. (Models\Timezone avoids the situation
    //		another way — it only uses HasAbbr, never this trait — because
    //		defining this accessor there would just be a pointless
    //		pass-through of the real 'abbreviation' column, not a bug.)
    //
    // @return string|null
    //
    public function getAbbreviationAttribute(): ?string
    {
        return $this->getAttributeFromArray(
            config('locale.abbreviations.'.$this->abbreviationConfigKey())
        );
    }
}

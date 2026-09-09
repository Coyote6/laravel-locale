<?php

namespace Coyote6\LaravelLocale\Concerns;

// Defines $model->abbr for any model that pairs this trait with a
// abbreviationConfigKey() method (see Models\Country, Models\State,
// Models\Timezone). Short-alias counterpart to HasAbbreviation — safe to
// use on its own (as Models\Timezone does) since 'abbr' never collides
// with a real column name here.
trait HasAbbr
{


    // Get Abbr Attribute
    //
    // Eloquent accessor for $model->abbr. Reads whichever field
    // config('locale.abbreviations.*') names for this model.
    //
    // @see \Coyote6\LaravelLocale\Concerns\HasAbbreviation
    //
    // @return string|null
    //
    public function getAbbrAttribute(): ?string
    {
        return $this->getAttributeFromArray(
            config('locale.abbreviations.'.$this->abbreviationConfigKey())
        );
    }
}

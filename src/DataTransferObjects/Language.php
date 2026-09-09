<?php

namespace Coyote6\LaravelLocale\DataTransferObjects;

// Not backed by a database table — see LocaleManager, which builds every
// instance of this class from Symfony Intl at runtime. abbreviation/abbr
// are computed from config('locale.abbreviations.languages') so this
// exposes the same property-style API as the Eloquent models'
// Concerns\HasAbbreviation / HasAbbr.
final class Language
{


    public readonly string $abbreviation;

    public readonly string $abbr;


    // Construct
    //
    // Sets id/name/direction directly from the constructor arguments, then
    // resolves abbreviation/abbr from config('locale.abbreviations.languages').
    //
    // @ai
    //		abbreviation/abbr can't be promoted constructor properties like
    //		id/name/direction — their value depends on config, not on an
    //		argument passed in — so they're assigned in the constructor body
    //		instead, both from the same resolved value.
    //
    // @param $id string - The ISO 639-1 code [Ex: en]
    // @param $name string - The language's English name [Ex: English]
    // @param $direction string - Script direction, 'ltr' or 'rtl' [Ex: ltr]
    //
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $direction,
    ) {
        $value = match (config('locale.abbreviations.languages')) {
            'name' => $this->name,
            default => $this->id,
        };

        $this->abbreviation = $value;
        $this->abbr = $value;
    }
}

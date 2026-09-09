<?php

namespace Coyote6\LaravelLocale;

use Coyote6\LaravelLocale\DataTransferObjects\Language;
use Symfony\Component\Intl\Languages as SymfonyLanguages;

// Language lookups, backed directly by Symfony Intl (official CLDR-sourced
// data) at runtime — no database table, no import/sync job, always current
// with whatever symfony/intl version is installed. This is the class the
// Locale facade (Facades\Locale) proxies to.
class LocaleManager
{


    // RTL Languages
    //
    // Script direction isn't part of Symfony Intl's Languages data — that's
    // a Scripts/ISO 15924 concern, not a 1:1 language lookup — so this list
    // is self-maintained rather than sourced. The set of RTL languages is
    // short and essentially static, unlike everything else in this package.
    protected const RTL_LANGUAGES = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'yi', 'ckb', 'dv'];


    // Language Exists
    //
    // Checks whether Symfony Intl recognizes the given code, accepting
    // either its ISO 639-1 or ISO 639-2/3 form (see resolveCode()).
    //
    // @see resolveCode()
    //
    // @param $code string - The language code to check [Ex: en or eng]
    //
    // @return bool
    //
    public function languageExists(string $code): bool
    {
        return $this->resolveCode($code) !== null;
    }


    // Language
    //
    // Looks up a single language by code and returns it as a Language DTO,
    // or null if Symfony Intl doesn't recognize the code in any form.
    //
    // @see resolveCode()
    // @see makeLanguage()
    //
    // @param $code string - The language code to look up [Ex: en or eng]
    //
    // @return \Coyote6\LaravelLocale\DataTransferObjects\Language|null
    //
    public function language(string $code): ?Language
    {
        $resolved = $this->resolveCode($code);

        if ($resolved === null) {
            return null;
        }

        return $this->makeLanguage($resolved, SymfonyLanguages::getName($resolved));
    }


    // Resolve Code
    //
    // Symfony Intl indexes most languages by their ISO 639-1 (two-letter)
    // code, but a language with no two-letter code is indexed by its
    // ISO 639-2/3 (three-letter) code instead. A caller can hand either
    // form; this tries the code as-is first, then falls back to
    // converting a three-letter code to its two-letter equivalent.
    //
    // @ai
    //		Added for GeoDataImporter::importCountryLanguages() — the
    //		mledoze/countries source (see config/locale.php's 'source'
    //		block) provides ISO 639-3 codes, ~73% of which only resolve
    //		this way, not directly. Kept here rather than duplicated in the
    //		importer, since any caller passing a three-letter code benefits
    //		from the same fallback.
    //
    // @param $code string - The language code to resolve [Ex: eng]
    //
    // @return string|null the code Symfony Intl actually recognizes, or null if neither form resolves
    //
    protected function resolveCode(string $code): ?string
    {
        if (SymfonyLanguages::exists($code)) {
            return $code;
        }

        try {
            $alpha2 = SymfonyLanguages::getAlpha2Code($code);

            if (SymfonyLanguages::exists($alpha2)) {
                return $alpha2;
            }
        } catch (\Throwable) {
            // No two-letter equivalent for this code — falls through to null.
        }

        return null;
    }


    // Languages
    //
    // Returns every language Symfony Intl knows about, keyed by ISO 639-1
    // code.
    //
    // @see makeLanguage()
    //
    // @return array<string, \Coyote6\LaravelLocale\DataTransferObjects\Language>
    //
    public function languages(): array
    {
        $languages = [];

        foreach (SymfonyLanguages::getNames() as $code => $name) {
            $languages[$code] = $this->makeLanguage($code, $name);
        }

        return $languages;
    }


    // Make Language
    //
    // Builds a Language DTO for the given code/name, resolving its script
    // direction from the self-maintained RTL_LANGUAGES list.
    //
    // @param $code string - The ISO 639-1 code [Ex: en]
    // @param $name string - The language's English name [Ex: English]
    //
    // @return \Coyote6\LaravelLocale\DataTransferObjects\Language
    //
    protected function makeLanguage(string $code, string $name): Language
    {
        return new Language(
            id: $code,
            name: $name,
            direction: in_array($code, self::RTL_LANGUAGES, true) ? 'rtl' : 'ltr',
        );
    }
}

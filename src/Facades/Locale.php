<?php

namespace Coyote6\LaravelLocale\Facades;

use Coyote6\LaravelLocale\LocaleManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Coyote6\LaravelLocale\DataTransferObjects\Language|null language(string $code)
 * @method static array<string, \Coyote6\LaravelLocale\DataTransferObjects\Language> languages()
 * @method static bool languageExists(string $code)
 *
 * @see LocaleManager
 */
class Locale extends Facade
{


    // Get Facade Accessor
    //
    // Tells Laravel's facade resolver which bound instance $this refers to
    // — LocaleServiceProvider::register() binds LocaleManager as a
    // singleton against this same class name.
    //
    // @return string
    //
    protected static function getFacadeAccessor(): string
    {
        return LocaleManager::class;
    }
}

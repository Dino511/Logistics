<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the app in the user's chosen language: the signed-in user's saved choice, or for
 * guests (the login page) the choice kept in their session. English is the default.
 * Translations live in lang/tl.json, keyed by the English text.
 */
class SetLocale
{
    public const LOCALES = ['en' => 'English', 'tl' => 'Tagalog'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', 'en');

        App::setLocale(array_key_exists($locale, self::LOCALES) ? $locale : 'en');

        return $next($request);
    }
}

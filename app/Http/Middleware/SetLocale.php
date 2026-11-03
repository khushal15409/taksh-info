<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page language: English, or Hindi when the visitor chose it in the language & theme settings (which sets the plain cookie
 * `taksh_lang=hi`; see bootstrap/app.php for why it is not encrypted). Any other value is English.
 */
class SetLocale
{
    public const COOKIE = 'taksh_lang';
    public const SUPPORTED = ['en', 'hi'];

    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->cookie(self::COOKIE);
        app()->setLocale(in_array($lang, self::SUPPORTED, true) ? $lang : config('app.locale', 'en'));

        return $next($request);
    }
}

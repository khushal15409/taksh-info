<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guests hitting a customer-only page go to the login page and come back afterwards (like the app's redirectAfter).
        // The page language: a plain (unencrypted) cookie set by the language settings, read before anything renders and applied to the HTML after.
        $middleware->encryptCookies(except: [\App\Http\Middleware\SetLocale::COOKIE]);
        $middleware->web(prepend: [\App\Http\Middleware\SetLocale::class], append: [\App\Http\Middleware\TranslateHtml::class]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('login', ['next' => $request->getRequestUri()]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON callers get the API envelope instead of a redirect.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('api.unauthenticated'), 'data' => null], 401);
            }
        });
    })->create();

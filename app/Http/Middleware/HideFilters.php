<?php

namespace App\Http\Middleware;

use App\Support\FilterToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hidefilters:scope` - keeps search / filter terms out of the address bar.
 *
 *  - POST (a search or filter form): the terms are packed into a token and the browser is sent (303) to the page's GET URL with `?f=token`.
 *  - GET with `?f=token`: the terms are put back into the request's query, so controllers and form requests read `$request->query('q')`
 *    exactly as before.
 *  - GET with the terms in plain sight (an old link, a bookmark, a script's link): packed and redirected (302) to the `?f=token` URL.
 *
 * App\Routing\KeyedUrlGenerator does the same packing for every route('listing', ['q' => ...]) the app itself builds.
 */
class HideFilters
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        if (! FilterToken::enabled()) {
            return $next($request);
        }

        $keys = FilterToken::keys($scope);

        if ($request->isMethod('POST')) {
            $terms = FilterToken::clean($scope, $request->post());
            $carry = array_filter($request->only((array) config('taksh.hide_filters.carry', [])), fn ($v) => is_string($v) && $v !== '');
            $token = FilterToken::encode($scope, $terms);

            return redirect()->to($request->url().($carry || $token ? '?'.http_build_query($carry + ($token ? ['f' => $token] : [])) : ''), 303);
        }

        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $plain = FilterToken::clean($scope, $request->query());
        if ($plain !== []) {
            $rest = array_diff_key($request->query(), array_flip($keys), ['f' => 1]);

            return redirect()->to($request->url().'?'.http_build_query($rest + ['f' => FilterToken::encode($scope, $plain)]), 302);
        }

        $token = $request->query('f');
        if (is_string($token) && $token !== '') {
            $request->query->add(FilterToken::decode($scope, $token) ?? []);
        }

        return $next($request);
    }
}

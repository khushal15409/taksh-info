<?php

namespace App\Http\Middleware;

use App\Support\UrlKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `urlkey:scope[,param[,lenient]]` - turns the opaque key in a page URL back into the row number before the controller runs, so controllers keep
 * working with plain ids. The key can be a path parameter ({id}) or a query value (?id=, ?order=).
 *
 *  - a valid key           -> replaced by the number
 *  - an old numeric link   -> (legacy on) a GET is redirected to the opaque URL, anything else carries on with the number
 *  - anything else         -> 404 for a path parameter; a query value is left alone (the page treats it as "not found")
 *
 * Generating URLs is the other half: App\Routing\KeyedUrlGenerator encodes the same parameter in route().
 */
class ResolveUrlKey
{
    public function handle(Request $request, Closure $next, string $scope, string $param = 'id', string $mode = 'strict'): Response
    {
        $route = $request->route();
        $inPath = in_array($param, $route->parameterNames(), true);
        $raw = $inPath ? $route->parameter($param) : $request->query($param);

        if (! is_string($raw) && ! is_int($raw)) {
            return $inPath ? abort(404) : $next($request);
        }
        $raw = (string) $raw;

        if (ctype_digit($raw)) {                       // an old, numeric link
            if (! UrlKey::enabled()) {
                return $next($request);
            }
            if (! UrlKey::legacyNumeric()) {
                return $inPath ? abort(404) : $next($request);
            }
            if ($request->isMethodSafe() && $route->getName()) {
                $params = $inPath ? array_merge($request->query(), $route->parameters(), [$param => $raw]) : $request->query();

                return redirect()->route($route->getName(), $params, 301);   // route() writes the opaque key
            }
            if ($inPath) {
                $route->setParameter($param, (int) $raw);
            }

            return $next($request);
        }

        $id = UrlKey::decode($scope, $raw);
        if ($id === null) {
            // not a key: a query value is left alone (e.g. an order NUMBER typed into ?id=), and so is a path value on a `lenient` route
            // (a booking can be opened by its booking number as well); anything else in a path is a 404
            return $inPath && $mode !== 'lenient' ? abort(404) : $next($request);
        }

        if ($inPath) {
            $route->setParameter($param, $id);
        } else {
            $request->query->set($param, (string) $id);
        }

        return $next($request);
    }
}

<?php

namespace App\Routing;

use App\Support\FilterToken;
use App\Support\UrlKey;
use Illuminate\Routing\Route;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;

/**
 * The normal URL generator, except that a route marked `urlkey:scope[,param]` gets that parameter written as an opaque key, so every
 * route('shop', $id) / route('tracking', ['id' => $id]) in the app produces /shops/k3x9... instead of /shops/12 without touching call sites.
 * A value that is already a key (or "slug-key"), or any non-numeric text, is left exactly as given.
 */
class KeyedUrlGenerator extends UrlGenerator
{
    public function toRoute($route, $parameters, $absolute)
    {
        if (UrlKey::enabled() && ($spec = self::specOf($route))) {
            $parameters = $this->keyed($route, Arr::wrap($parameters), $spec[0], $spec[1]);
        }

        if (FilterToken::enabled() && ($scope = self::middlewareArg($route, 'hidefilters:'))) {
            $parameters = $this->packed($scope, Arr::wrap($parameters));
        }

        return parent::toRoute($route, $parameters, $absolute);
    }

    /** route('listing', ['q' => 'tea', 'category' => [3]]) -> ?f=<token>: the terms of a `hidefilters` page never go into a link as text. */
    private function packed(string $scope, array $parameters): array
    {
        $keys = FilterToken::keys($scope);
        $terms = FilterToken::clean($scope, array_intersect_key($parameters, array_flip($keys)));
        if (array_key_exists('f', $parameters)) {
            return $parameters;
        }

        $rest = array_diff_key($parameters, array_flip($keys));   // empty terms (?q=) are dropped too

        return $terms === [] ? $rest : $rest + ['f' => FilterToken::encode($scope, $terms)];
    }

    private static function middlewareArg(Route $route, string $prefix): ?string
    {
        foreach ((array) $route->middleware() as $m) {
            if (is_string($m) && str_starts_with($m, $prefix)) {
                return substr($m, strlen($prefix));
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null [scope, parameter] */
    private static function specOf(Route $route): ?array
    {
        foreach ((array) $route->middleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'urlkey:')) {
                $args = explode(',', substr($m, 7));

                return [$args[0], $args[1] ?? 'id'];
            }
        }

        return null;
    }

    private function keyed(Route $route, array $parameters, string $scope, string $param): array
    {
        $names = $route->parameterNames();
        $key = array_key_exists($param, $parameters) ? $param : null;

        // positional arguments, e.g. route('sales.detail', $id): map them onto the route's parameter names
        if ($key === null && ($i = array_search($param, $names, true)) !== false && array_key_exists($i, $parameters) && array_is_list($parameters)) {
            $key = $i;
        }

        if ($key !== null && (is_int($parameters[$key]) || (is_string($parameters[$key]) && ctype_digit($parameters[$key])))) {
            $parameters[$key] = UrlKey::encode($scope, (int) $parameters[$key]);
        }

        return $parameters;
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\HindiPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Hindi pages: translates the HTML of a page response from lang/hi.json (see HindiPage). English visitors cost nothing. */
class TranslateHtml
{
    public function __construct(private readonly HindiPage $pages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (app()->getLocale() === 'en' || $request->expectsJson() || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html') || $response->isRedirection()) {
            return $response;
        }

        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $response->setContent($this->pages->translate($content));
        }

        return $response;
    }
}

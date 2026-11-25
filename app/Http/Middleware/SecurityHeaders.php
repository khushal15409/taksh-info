<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every page and ajax answer: a Content-Security-Policy (scripts only from this site, the few
 * third parties the shop really uses, and inline scripts that carry the per-request nonce), plus the usual hardening headers.
 *
 * What the pages legitimately load, so nothing is blocked:
 *  - Razorpay checkout (script, its payment frame, its API calls)
 *  - Leaflet from cdnjs + OpenStreetMap tiles (address map)
 *  - Google Fonts (CSS + font files)
 *  - product / banner images from any https host (the media server)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // one random value per request; inline <script nonce="..."> in the views must carry it
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), usb=(), interest-cohort=()');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure() && config('security.hsts_max_age') > 0) {
            $headers->set('Strict-Transport-Security', 'max-age='.config('security.hsts_max_age').'; includeSubDomains');
        }

        $mode = config('security.csp');
        if ($mode !== 'off' && ! $this->isDownload($response)) {
            $headers->set($mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', $this->policy($nonce, $request->isSecure()));
        }

        header_remove('X-Powered-By');

        return $response;
    }

    private function policy(string $nonce, bool $secure): string
    {
        $razorpay = 'https://checkout.razorpay.com https://api.razorpay.com';

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://checkout.razorpay.com https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            "connect-src 'self' {$razorpay} https://lumberjack.razorpay.com",
            "frame-src {$razorpay}",
            "worker-src 'self' blob:",
            "form-action 'self' https://api.razorpay.com",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
        ];

        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /** Files (invoice PDFs, images served through PHP) carry no page, so a page policy adds nothing. */
    private function isDownload(Response $response): bool
    {
        return $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse;
    }
}

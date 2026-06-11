<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce per request pro inline <script> – musí vzniknout PŘED renderem
        // view. @vite i @livewireScripts si ho z Vite::cspNonce() přeberou samy,
        // vlastní inline skripty ho vkládají direktivou @cspNonce.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Prevent plugin-based attacks, base-tag injection, and framing by foreign origins.
        // jsdelivr.net: swagger-ui (api-docs).
        // tile.openstreetmap.org: Leaflet map tiles (img-src + connect-src).
        // 'unsafe-inline' for style-src: required by Leaflet and inline style attributes.
        //
        // script-src: místo 'unsafe-inline' per-request nonce – inline skript bez
        // nonce (typický payload XSS) prohlížeč nespustí. Externí skripty kryje
        // host-source (cdn.jsdelivr.net).
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net",
            "img-src 'self' data: https://tile.openstreetmap.org",
            "font-src 'self' data:",
            "connect-src 'self' https://tile.openstreetmap.org",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
        ]));

        // HSTS: only over HTTPS; 6 months, include subdomains.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15768000; includeSubDomains');
        }

        return $response;
    }
}

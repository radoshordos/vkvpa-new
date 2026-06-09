<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Prevent plugin-based attacks, base-tag injection, and framing by foreign origins.
        // jsdelivr.net: chart.js (admin dashboard) + swagger-ui (api-docs).
        // tile.openstreetmap.org: Leaflet map tiles (img-src + connect-src).
        // 'unsafe-inline' for style-src: required by Leaflet and inline style attributes.
        //
        // Laravel Pulse bundluje Alpine.js, který vyhodnocuje x-data výrazy přes new Function()
        // – to vyžaduje 'unsafe-eval'. Přidáváme ho cíleně jen pro /pulse/* cestu,
        // aby hlavní aplikace zůstala s přísnějším pravidlem.
        $pulsePathRaw = config('pulse.path', 'pulse');
        $pulsePath = is_string($pulsePathRaw) ? $pulsePathRaw : 'pulse';
        $isPulse = str_starts_with($request->path(), $pulsePath);
        $scriptSrc = $isPulse
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net"
            : "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net";

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            $scriptSrc,
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

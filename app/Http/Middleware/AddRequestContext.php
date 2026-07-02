<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AddRequestContext
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $this->traceId($request);
        $route = $request->route();
        $user = $request->user();

        $context = [
            'trace_id' => $traceId,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
        ];

        if ($route instanceof Route && $route->getName() !== null) {
            $context['route_name'] = $route->getName();
        }

        if ($user !== null) {
            $userId = $user->getAuthIdentifier();

            if (is_int($userId) || is_string($userId)) {
                $context['user_id'] = $userId;
            }
        }

        if ($user instanceof User && $user->is_admin) {
            $context['admin_id'] = $user->id;
            $context['admin_name'] = $user->name;
        }

        Context::add($context);
        Context::addHidden([
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $traceId);

        return $response;
    }

    private function traceId(Request $request): string
    {
        $candidate = trim((string) $request->headers->get('X-Request-Id', ''));

        if (preg_match('/\A[A-Za-z0-9._:-]{8,128}\z/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::uuid();
    }
}

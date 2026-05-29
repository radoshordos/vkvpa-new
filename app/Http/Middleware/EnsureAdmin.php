<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chrání administrátorské stránky (Fáze 4).
 * Nahrazuje legacy kontrolu $_SESSION['prihlasen'] == 'Beda'.
 *
 * Registrace aliasu v bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class]);
 *   })
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || ! $user->is_admin) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}

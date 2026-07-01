<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * Audit log admin akcí – doplňuje jméno přihlášeného admina ke kontextu.
 */
final class AdminLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $action, array $context = []): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            Context::add([
                'admin_id' => $user->id,
                'admin_name' => $user->name,
            ]);
        }

        Log::info($action, [
            ...Context::only(['trace_id', 'route_name', 'request_path', 'admin_id', 'admin_name']),
            ...$context,
            'admin' => $user instanceof User ? $user->name : null,
        ]);
    }
}

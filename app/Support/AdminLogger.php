<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Audit log admin akcí – doplňuje jméno přihlášeného admina ke kontextu.
 */
final class AdminLogger
{
    public static function log(string $action, array $context = []): void
    {
        Log::info($action, [...$context, 'admin' => Auth::user()?->name]);
    }
}

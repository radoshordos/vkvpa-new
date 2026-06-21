<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sanitizace názvů souborů odvozených z uživatelského vstupu (značka stanice)
 * pro použití v Content-Disposition / ZIP položkách – zabraňuje header injection.
 */
final class FileName
{
    public static function sanitize(string $name, string $fallback = 'denik'): string
    {
        $base = $name !== '' ? $name : $fallback;

        return preg_replace('/[^A-Za-z0-9\-]/', '_', $base) ?? $fallback;
    }
}

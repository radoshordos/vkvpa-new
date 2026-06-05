<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ověří, že hodnota je platný Maidenhead lokátor (4 nebo 6 znaků).
 */
final class ValidMaidenhead implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-R]{2}[0-9]{2}([A-X]{2})?$/i', $value)) {
            $fail('Lokátor musí být platný Maidenhead formát (např. JN79 nebo JN79XW).');
        }
    }
}

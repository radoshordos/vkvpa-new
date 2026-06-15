<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ověří, že hodnota je platné telefonní číslo.
 *
 * Akceptuje mezinárodní i národní formát s volitelnou předvolbou „+",
 * mezerami, pomlčkami, lomítky a závorkami (např. „+420 777 123 456",
 * „+420123456789", „777/123/456"). Vyžaduje 9–15 číslic.
 */
final class ValidPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Telefon musí být řetězec.');

            return;
        }

        // Povolené znaky: číslice, mezery, +, -, /, (, ) a tečka.
        if (! preg_match('/^\+?[0-9 ()\/.\-]+$/', $value)) {
            $fail('Telefon smí obsahovat jen číslice, mezery a znaky + - / ( ).');

            return;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) < 9 || strlen($digits) > 15) {
            $fail('Telefon musí obsahovat 9 až 15 číslic.');
        }
    }
}

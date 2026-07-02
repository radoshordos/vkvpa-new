<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Override;

/**
 * Jednorázové přihlašovací tokeny (magic-link) navázané na uživatele.
 *
 * Token má tvar selector+verifier: prvních {@see self::SELECTOR_LENGTH} znaků je
 * veřejný „selector" pro vyhledání řádku, zbytek je „verifier" ověřovaný proti
 * argon2id hashi ve sloupci `token` (preferován před SHA-2 – únik DB nevydá
 * použitelné tokeny a argon2 je odolný vůči GPU/ASIC útokům).
 *
 * @property int $id
 * @property string $selector
 * @property string $token
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['selector', 'token', 'user_id'])]
class LoginToken extends Model
{
    /** Délka veřejného selectoru (prefix plaintext tokenu). */
    public const SELECTOR_LENGTH = 16;

    /** Délka tajného verifieru (zbytek plaintext tokenu). */
    public const VERIFIER_LENGTH = 32;

    #[Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    /**
     * Vytvoří nový token navázaný na uživatele a vrátí jeho plaintext podobu
     * (selector+verifier) pro odeslání e-mailem. Verifier se ukládá hashovaný.
     */
    public static function issue(?int $userId): string
    {
        $selector = Str::password(self::SELECTOR_LENGTH, letters: true, numbers: true, symbols: false);
        $verifier = Str::password(self::VERIFIER_LENGTH, letters: true, numbers: true, symbols: false);

        self::create([
            'selector' => $selector,
            'token' => Hash::make($verifier),
            'user_id' => $userId,
        ]);

        return $selector.$verifier;
    }
}

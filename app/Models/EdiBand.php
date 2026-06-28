<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Číselník pásem (band). Vytažený z denormalizovaného textového sloupce
 * `edi_category.band`; kategorie na něj ukazuje přes `edi_category.band_id`.
 *
 * Pásmo je statický číselník – kanonický seznam žije v {@see self::CANONICAL}
 * a slouží jako jediný zdroj pravdy pro seeder i validaci admin formuláře.
 *
 * @property int $id
 * @property string $token kanonický token bez jednotky ('144', '432', '1.3', … '122')
 * @property string $name čitelný štítek s jednotkou ('144 MHz', '1.3 GHz', … '122 GHz')
 */
#[Fillable(['id', 'token', 'name'])]
#[Table(name: 'edi_bands', key: 'id')]
#[WithoutTimestamps]
class EdiBand extends Model
{
    /**
     * Kanonický seznam pásem: id → [token, name]. Pořadí (144 → 122 GHz)
     * odpovídá id, takže `orderBy('id')` dává přirozené řazení pásem.
     *
     * @var array<int, array{string, string}>
     */
    public const array CANONICAL = [
        1 => ['144', '144 MHz'],
        2 => ['432', '432 MHz'],
        3 => ['1.3', '1.3 GHz'],
        4 => ['2.3', '2.3 GHz'],
        5 => ['3.4', '3.4 GHz'],
        6 => ['5.7', '5.7 GHz'],
        7 => ['10', '10 GHz'],
        8 => ['24', '24 GHz'],
        9 => ['47', '47 GHz'],
        10 => ['76', '76 GHz'],
        11 => ['122', '122 GHz'],
    ];

    /**
     * id pásma podle kanonického tokenu (pro párování ve seederu kategorií).
     * Vyhledává v {@see self::CANONICAL}, aby se vyhnul přetypování numerických
     * string klíčů ('144' → int) v PHP poli.
     */
    public static function idForToken(string $token): int
    {
        foreach (self::CANONICAL as $id => [$t]) {
            if ($t === $token) {
                return $id;
            }
        }

        throw new InvalidArgumentException(sprintf('Neznámý token pásma „%s“.', $token));
    }

    /**
     * Kategorie využívající toto pásmo.
     *
     * @return HasMany<EdiCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(EdiCategory::class, 'band_id', 'id');
    }
}

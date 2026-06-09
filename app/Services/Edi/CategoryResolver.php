<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Exceptions\UnknownBandException;
use App\Support\VkvpaSettings;

/**
 * Určení kategorie závodu z hlavičky EDI deníku.
 *
 * Kategorie je vlastnost závodu; robot ji z deníku jen odvodí podle tří vstupů:
 *   1. PÁSMO   z `PBand` – tolerantně (hovorové „145"→144, „435"→432, čárka→tečka);
 *              nerozpoznané pásmo → {@see UnknownBandException} (deník se odmítne).
 *   2. SEKCE   z `PSect` – „MULTI"/začíná „M" → multi op; „SINGLE"/„SO"/začíná „S"
 *              → single op; cokoliv jiného (prázdné, „01") → null (nezařazeno,
 *              kategorii doplní admin). Přípony výkonu (LP/HIGH) i „DX" se ignorují.
 *   3. DX      podle prefixu značky `PCall` – nezačíná-li na „OK" ani „OL“ (= cizí
 *              stanice), použije se DX varianta kategorie.
 *
 * Výsledkem je id záznamu z tabulky `vkvpa_kategorie` (viz seed), nebo null,
 * když sekci/kombinaci nelze určit.
 */
final class CategoryResolver
{
    /**
     * Aliasy pásem (klíč = velkými písmeny, oříznuto) → kanonické pásmo.
     */
    private const array BANDS = [
        '144 MHZ' => '144', '145 MHZ' => '144', '144' => '144', '145' => '144',
        '432 MHZ' => '432', '435 MHZ' => '432', '432' => '432', '435' => '432',
        '1,3 GHZ' => '1.3', '1.3 GHZ' => '1.3',
        '2,3 GHZ' => '2.3', '2.3 GHZ' => '2.3',
        '3,4 GHZ' => '3.4', '3.4 GHZ' => '3.4',
        '5,7 GHZ' => '5.7', '5.7 GHZ' => '5.7',
        '10 GHZ' => '10',
        '24 GHZ' => '24',
        '47 GHZ' => '47',
        '76 GHZ' => '76',
        '122 GHZ' => '122',
    ];

    /**
     * Kanonické pásmo → sekce (SO/MO) → varianta (op/dx) → id kategorie ze seedu.
     * (PHP převádí numerické klíče jako „144" na int, proto int|string.)
     *
     * ID odpovídají záznamům v `vkvpa_kategorie` – viz database/seeders/VkvpaKategorieTableSeeder.php.
     * Při přidání nové kategorie: 1) přidej řádek do seederu, 2) doplň ID do této matice.
     *
     * @var array<int|string, array<string, array<string, int>>>
     */
    private const array CATEGORIES = [
        '144' => ['SO' => ['op' => 1, 'dx' => 23], 'MO' => ['op' => 2, 'dx' => 24]],
        '432' => ['SO' => ['op' => 3, 'dx' => 25], 'MO' => ['op' => 4, 'dx' => 26]],
        '1.3' => ['SO' => ['op' => 5, 'dx' => 27], 'MO' => ['op' => 6, 'dx' => 28]],
        '2.3' => ['SO' => ['op' => 7, 'dx' => 29], 'MO' => ['op' => 8, 'dx' => 30]],
        '3.4' => ['SO' => ['op' => 9, 'dx' => 31], 'MO' => ['op' => 10, 'dx' => 32]],
        '5.7' => ['SO' => ['op' => 11, 'dx' => 33], 'MO' => ['op' => 12, 'dx' => 34]],
        '10' => ['SO' => ['op' => 13, 'dx' => 35], 'MO' => ['op' => 14, 'dx' => 36]],
        '24' => ['SO' => ['op' => 15, 'dx' => 38], 'MO' => ['op' => 16, 'dx' => 39]],
        '47' => ['SO' => ['op' => 17, 'dx' => 42], 'MO' => ['op' => 18, 'dx' => 43]],
        '76' => ['SO' => ['op' => 19, 'dx' => 45], 'MO' => ['op' => 20, 'dx' => 44]],
        '122' => ['SO' => ['op' => 21], 'MO' => ['op' => 22]], // 122 GHz nemá DX kategorii
    ];

    /**
     * Vrátí všechna ID kategorií použitá v matici CATEGORIES.
     * Slouží k ověření konzistence s tabulkou vkvpa_kategorie.
     *
     * @return list<int>
     */
    public static function allCategoryIds(): array
    {
        $ids = [];
        foreach (self::CATEGORIES as $sections) {
            foreach ($sections as $variants) {
                foreach ($variants as $id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  string  $pcall  volací značka (PCall) – pro určení DX
     * @param  string  $pBand  pásmo z hlavičky (PBand)
     * @param  string  $pSect  sekce z hlavičky (PSect)
     * @return int|null id kategorie, nebo null když sekci/kombinaci nelze určit
     *
     * @throws UnknownBandException pásmo nelze zařadit → deník se odmítne
     */
    public function resolve(string $pcall, string $pBand, string $pSect): ?int
    {
        $band = $this->band($pBand);
        $section = $this->section($pSect);

        if ($section === null) {
            return null; // sekci nelze určit → kategorii doplní admin
        }

        $variant = $this->isDx($pcall) ? 'dx' : 'op';

        return self::CATEGORIES[$band][$section][$variant] ?? null;
    }

    /** Normalizace pásma na kanonický klíč; nerozpoznané → výjimka. */
    private function band(string $pBand): string
    {
        $key = strtoupper(trim($pBand));

        return self::BANDS[$key]
            ?? throw new UnknownBandException(sprintf('Nerozpoznané pásmo „%s“.', $pBand));
    }

    /** Sekce: 'MO' (multi), 'SO' (single), nebo null (nerozpoznané/prázdné). */
    private function section(string $pSect): ?string
    {
        $p = strtoupper(trim($pSect));

        if ($p === '') {
            return null;
        }
        if (str_contains($p, 'MULTI') || str_starts_with($p, 'M')) {
            return 'MO';
        }
        if (str_contains($p, 'SINGLE') || str_contains($p, 'SO') || str_starts_with($p, 'S')) {
            return 'SO';
        }

        return null;
    }

    /** DX = značka nezačíná žádným z tuzemských prefixů (config vkvpa.domestic_prefixes). */
    private function isDx(string $pcall): bool
    {
        $p = strtoupper(trim($pcall));

        return ! array_any(
            VkvpaSettings::domesticPrefixes(),
            static fn (string $prefix): bool => str_starts_with($p, strtoupper($prefix)),
        );
    }
}

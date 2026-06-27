<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Exceptions\UnknownBandException;
use App\Models\EdiCategory;
use App\Support\VkvpaSettings;
use Illuminate\Support\Facades\Cache;

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
 * Výsledkem je id záznamu z tabulky `edi_category` (viz seed), nebo null,
 * když sekci/kombinaci nelze určit. `edi_category` je jediný číselník kategorií
 * (na něj míří FK `vkvpa_data.id_kategorie`).
 */
final class CategoryResolver
{
    /** Cache klíč mapy „band|section|variant" → id (číselník je statický). */
    private const string CACHE_KEY = 'edi_category_map';

    /**
     * Aliasy pásem (klíč = velkými písmeny, oříznuto) → kanonické pásmo
     * s jednotkou, shodné se sloupcem `edi_category.band`.
     */
    private const array BANDS = [
        '144 MHZ' => '144 MHz', '145 MHZ' => '144 MHz', '144' => '144 MHz', '145' => '144 MHz',
        '432 MHZ' => '432 MHz', '435 MHZ' => '432 MHz', '432' => '432 MHz', '435' => '432 MHz',
        '1,3 GHZ' => '1.3 GHz', '1.3 GHZ' => '1.3 GHz',
        '2,3 GHZ' => '2.3 GHz', '2.3 GHZ' => '2.3 GHz',
        '3,4 GHZ' => '3.4 GHz', '3.4 GHZ' => '3.4 GHz',
        '5,7 GHZ' => '5.7 GHz', '5.7 GHZ' => '5.7 GHz',
        '10 GHZ' => '10 GHz',
        '24 GHZ' => '24 GHz',
        '47 GHZ' => '47 GHz',
        '76 GHZ' => '76 GHz',
        '122 GHZ' => '122 GHz',
    ];

    /**
     * Vrátí všechna id kategorií z tabulky `edi_category`.
     *
     * @return list<int>
     */
    public static function allCategoryIds(): array
    {
        $ids = EdiCategory::query()
            ->orderBy('id')
            ->get(['id'])
            ->map(static fn (EdiCategory $c): int => $c->id)
            ->all();

        return array_values($ids);
    }

    /** Zahodí cachovanou mapu kategorií (volat po reseedu/úpravě `edi_category`). */
    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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

        $variant = $this->isDx($pcall) ? 'dx' : 'domestic';

        return $this->categoryMap()["{$band}|{$section}|{$variant}"] ?? null;
    }

    /**
     * Mapa „band|section|variant" → id z `edi_category`, cachovaná napořád
     * (číselník je statický). Drží jen skaláry, takže projde i přes
     * `cache.serializable_classes=false`.
     *
     * @return array<string, int>
     */
    private function categoryMap(): array
    {
        /** @var array<string, int> */
        return Cache::rememberForever(self::CACHE_KEY, static fn (): array => EdiCategory::query()
            ->get(['id', 'band', 'section', 'variant'])
            ->mapWithKeys(static fn (EdiCategory $c): array => [
                "{$c->band}|{$c->section}|{$c->variant}" => $c->id,
            ])
            ->all());
    }

    /** Normalizace pásma na kanonický klíč; nerozpoznané → výjimka. */
    private function band(string $pBand): string
    {
        $key = strtoupper(trim($pBand));

        // vlož mezeru mezi číslo (vč. „1,3"/„1.3") a jednotku: „47GHZ" → „47 GHZ"
        $key = (string) preg_replace('/([\d,.]+)\s*(MHZ|GHZ)/', '$1 $2', $key);
        // sjednoť vícenásobné mezery na jednu
        $key = (string) preg_replace('/\s+/', ' ', $key);

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

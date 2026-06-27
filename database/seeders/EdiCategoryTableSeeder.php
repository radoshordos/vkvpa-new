<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Edi\CategoryResolver;

/**
 * Naplní `edi_category` přemapováním 42 řádků z `vkvpa_kategorie`.
 *
 * Původní `id` se zachovávají (včetně historických mezer 37/40/41), aby šlo
 * případné `vkvpa_data.id_kategorie` později přesměrovat 1:1. `band` nese
 * jednotku ('144 MHz' / '1.3 GHz'), `name` se generuje jednotně z os (odpadají
 * historické překlepy). `dxid` u DX řádků ukazuje na tuzemský protějšek se
 * shodným band+section; u tuzemských řádků je NULL. Pásmo 122 GHz nemá DX.
 */
class EdiCategoryTableSeeder extends JsonTableSeeder
{
    protected string $table = 'edi_category';

    protected ?int $autoIncrement = 46;

    public function run(): void
    {
        parent::run();

        // číselník se cachuje v CategoryResolveru – po reseedu ho zahoď
        CategoryResolver::forgetCache();
    }

    /** Kanonický token pásma → čitelný štítek pro název. */
    private const array BAND_LABELS = [
        '144' => '144 MHz', '432' => '432 MHz',
        '1.3' => '1.3 GHz', '2.3' => '2.3 GHz', '3.4' => '3.4 GHz', '5.7' => '5.7 GHz',
        '10' => '10 GHz', '24' => '24 GHz', '47' => '47 GHz', '76' => '76 GHz', '122' => '122 GHz',
    ];

    /**
     * Mapa původní id → [band, section, variant]. Pořadí pásem odpovídá
     * původnímu číselníku; variant 'domestic' = id 1–22, 'dx' = id 23–45.
     *
     * @var array<int, array{string, string, string}>
     */
    private const array MAP = [
        1 => ['144', 'SO', 'domestic'], 2 => ['144', 'MO', 'domestic'],
        3 => ['432', 'SO', 'domestic'], 4 => ['432', 'MO', 'domestic'],
        5 => ['1.3', 'SO', 'domestic'], 6 => ['1.3', 'MO', 'domestic'],
        7 => ['2.3', 'SO', 'domestic'], 8 => ['2.3', 'MO', 'domestic'],
        9 => ['3.4', 'SO', 'domestic'], 10 => ['3.4', 'MO', 'domestic'],
        11 => ['5.7', 'SO', 'domestic'], 12 => ['5.7', 'MO', 'domestic'],
        13 => ['10', 'SO', 'domestic'], 14 => ['10', 'MO', 'domestic'],
        15 => ['24', 'SO', 'domestic'], 16 => ['24', 'MO', 'domestic'],
        17 => ['47', 'SO', 'domestic'], 18 => ['47', 'MO', 'domestic'],
        19 => ['76', 'SO', 'domestic'], 20 => ['76', 'MO', 'domestic'],
        21 => ['122', 'SO', 'domestic'], 22 => ['122', 'MO', 'domestic'],

        23 => ['144', 'SO', 'dx'], 24 => ['144', 'MO', 'dx'],
        25 => ['432', 'SO', 'dx'], 26 => ['432', 'MO', 'dx'],
        27 => ['1.3', 'SO', 'dx'], 28 => ['1.3', 'MO', 'dx'],
        29 => ['2.3', 'SO', 'dx'], 30 => ['2.3', 'MO', 'dx'],
        31 => ['3.4', 'SO', 'dx'], 32 => ['3.4', 'MO', 'dx'],
        33 => ['5.7', 'SO', 'dx'], 34 => ['5.7', 'MO', 'dx'],
        35 => ['10', 'SO', 'dx'], 36 => ['10', 'MO', 'dx'],
        38 => ['24', 'SO', 'dx'], 39 => ['24', 'MO', 'dx'],
        42 => ['47', 'SO', 'dx'], 43 => ['47', 'MO', 'dx'],
        45 => ['76', 'SO', 'dx'], 44 => ['76', 'MO', 'dx'],
    ];

    /**
     * @return list<array{id: int, band: string, section: string, variant: string, name: string, dxid: int|null}>
     */
    protected function rows(): array
    {
        // tuzemský protějšek pro DX řádek: "token|section" → id tuzemské kategorie
        $domesticId = [];
        foreach (self::MAP as $id => [$token, $section, $variant]) {
            if ($variant === 'domestic') {
                $domesticId["{$token}|{$section}"] = $id;
            }
        }

        $rows = [];
        foreach (self::MAP as $id => [$token, $section, $variant]) {
            $rows[] = [
                'id' => $id,
                'band' => self::BAND_LABELS[$token],
                'section' => $section,
                'variant' => $variant,
                'name' => $this->name($token, $section, $variant),
                'dxid' => $variant === 'dx' ? ($domesticId["{$token}|{$section}"] ?? null) : null,
            ];
        }

        return $rows;
    }

    private function name(string $token, string $section, string $variant): string
    {
        $label = self::BAND_LABELS[$token];
        $sect = $section === 'MO' ? 'multi op' : 'single op';
        $dx = $variant === 'dx' ? ' DX' : '';

        return sprintf('%s %s%s', $label, $sect, $dx);
    }
}

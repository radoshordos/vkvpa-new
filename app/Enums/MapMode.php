<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Režim mapového pohledu na spojení stanice – tři akce MapController.
 *
 * Hodnota (`value`) je zároveň segment URL i klíč pro JS v šabloně mapy:
 *   Jezek     (M) – QTH uprostřed, čáry (paprsky) do protistanic
 *   Spendliky (N) – špendlíky protistanic; popup = značka, vzdálenost, azimut
 *   Lokatory  (S) – velké čtverce (lokátory) s počtem protistanic
 */
enum MapMode: string
{
    case Jezek = 'jezek';
    case Spendliky = 'spendliky';
    case Lokatory = 'lokatory';

    /**
     * Popisek režimu pod nadpisem mapy.
     */
    public function label(): string
    {
        return match ($this) {
            self::Jezek => 'ježek – čáry do protistanic',
            self::Spendliky => 'špendlíky – značka, vzdálenost, azimut',
            self::Lokatory => 'velké čtverce – počet protistanic',
        };
    }
}

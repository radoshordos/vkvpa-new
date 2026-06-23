<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Edi\EdiComposer;
use App\Services\Edi\EdiParser;

/**
 * Písmeno na třetím (tónovém) místě RST reportu, kterým se u CW provozu
 * nahrazuje číslice tónu (T) při šíření zkreslujícím signál.
 *
 * Definice přebírá VHF Handbook (Signal reporting): tónová složka RST se
 * rozšiřuje o „a" (auroral propagation), „s" (scatter propagation) a „m"
 * (multipath propagation). Jde o mezinárodní definice – popisky se nepřekládají.
 * Reporty se ukládají velkými písmeny (parser celý řádek uppercasuje), proto
 * hodnoty (`value`) jsou A/S/M.
 *
 * Tento enum je jediným zdrojem pravdy o povolených písmenech reportu –
 * konzumuje ho parser ({@see EdiParser}) i skladač
 * ({@see EdiComposer}).
 */
enum RstPropagation: string
{
    case Aurora = 'A';
    case Scatter = 'S';
    case Multipath = 'M';

    /**
     * Povolená písmena jako řetězec znaků pro vložení do regulárního výrazu
     * (znaková třída bez závorek), např. „ASM".
     */
    public static function letters(): string
    {
        return implode('', array_map(static fn (self $c): string => $c->value, self::cases()));
    }

    /**
     * Je report platný? Přípustný je číselný report, volitelně zakončený
     * jedním povoleným tónovým písmenem (A/S/M). Prázdný report je platný
     * (neúplné spojení se řeší jinde – {@see QsoCountStatus::IncompleteExchange}).
     */
    public static function isValidReport(string $report): bool
    {
        $report = strtoupper(trim($report));

        return preg_match('/^[0-9]*['.self::letters().']?$/', $report) === 1;
    }

    /**
     * Tónové písmeno reportu jako enum, nebo null když report žádné nemá
     * (běžný číselný report) či je neplatné.
     */
    public static function tryFromReport(string $report): ?self
    {
        $report = strtoupper(trim($report));
        $last = $report === '' ? '' : substr($report, -1);

        return self::tryFrom($last);
    }

    /**
     * Mezinárodní popisek šíření (nepřekládá se), shodný s VHF Handbookem.
     */
    public function label(): string
    {
        return match ($this) {
            self::Aurora => 'auroral propagation',
            self::Scatter => 'scatter propagation',
            self::Multipath => 'multipath propagation',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Exceptions\EdiParseException;
use Illuminate\Support\Facades\Log;

/**
 * Parser EDI deníku (formát REG1TEST). Čistá, testovatelná služba bez DB a výstupu.
 */
final class EdiParser
{
    /**
     * Regex jednoho QSO řádku – 15 skupin přesně dle původního read_edi.php.
     */
    private const string QSO_PATTERN =
        '/^([0-9]+);([0-9]+);([0-9A-Z\/]+);([0-9]*);([0-9]+[AS]?);([0-9]+);'
        .'([0-9]+[AS]?);([0-9]+);([0-9]*);([A-Z]{2}[0-9]{2}[A-Z]{2});([0-9]+);'
        .'([A-Z0-9]*);([A-Z0-9]*);([A-Z0-9]*);([A-Z0-9]*)/';

    /**
     * Naparsuje obsah EDI souboru.
     *
     * @throws EdiParseException při nesouladu deklarovaného a skutečného počtu QSO
     */
    public function parse(string $content): EdiLog
    {
        // Sjednocení kódování na UTF-8 – část deníků chodí ve Windows-1250
        // (česká diakritika v RName/Remarks by se jinak rozbila). mbstring
        // středoevropské CP1250 neumí, proto iconv.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $converted = iconv('Windows-1250', 'UTF-8//TRANSLIT', $content);
            if ($converted !== false) {
                $content = $converted;
            } else {
                Log::warning('EdiParser: iconv() failed to convert EDI content from Windows-1250 to UTF-8; output may be garbled.');
            }
        }

        $section = '';
        $fields = [];
        $qsos = [];
        $ignored = [];
        $lineErrors = [];
        $declaredTotal = 0;
        $raw = '';

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $buf = trim(str_replace("\xEF\xBB\xBF", '', $line));
            $raw .= $buf."\n";

            if ($section === 'head' && preg_match('/^(.+)=(.*)$/', $buf, $m)) {
                $fields[$m[1]] = $m[2];
            }

            if (str_starts_with($buf, '[END')) {
                $section = 'end';
            }

            if ($section === 'records') {
                $upper = strtoupper($buf);
                if (str_contains($upper, 'ERROR') || str_contains($upper, 'EROR')) {
                    // Řádek s chybovou značkou (vadné spojení) – ignorujeme i kdyby
                    // jinak vyhověl regexu, protože značka „ERROR" stojí v poli
                    // volačky a řádek by se jinak započítal jako platné QSO.
                    if ($buf !== '') {
                        $ignored[] = $buf;
                    }
                } elseif (preg_match(self::QSO_PATTERN, $upper, $m)) {
                    // Lokátor musí být platný Maidenhead: první 2 písmena A–R,
                    // číslice 0–9, subčtverec A–X. Jinak je QSO odmítnuto.
                    if (preg_match('/^[A-R]{2}[0-9]{2}([A-X]{2})?$/', $m[10]) !== 1) {
                        $lineErrors[] = 'QSO s '.$m[3].' odmítnuto: lokátor „'.$m[10]
                            .'" není platný Maidenhead (první 2 písmena musí být A–R, subčtverec A–X).';
                    } else {
                        $qsos[] = EdiQso::fromMatch($m);
                    }
                } elseif ($buf !== '') {
                    // Neparsovatelný řádek (prázdná povinná pole apod.) –
                    // přeskočíme a importujeme zbytek deníku.
                    $ignored[] = $buf;
                }
            }

            if (str_starts_with($buf, '[REG1TEST')) {
                $section = 'head';
            }

            if ($buf === '[Remarks]') {
                $section = 'remarks';
            }

            if (str_starts_with($buf, '[QSORecords;')) {
                $section = 'records';
                $declaredTotal = (int) substr($buf, 12);
            }
        }

        // Žádné platné ani odmítnuté spojení, ač deník nějaká deklaruje → soubor
        // nemá platnou strukturu EDI (např. odeslaný .txt místo .edi) → odmítnout.
        // Pokud jsou QSO odmítnuta pro neplatný lokátor ($lineErrors), soubor
        // je validní EDI – pokračujeme a závodník uvidí varování.
        if ($qsos === [] && $lineErrors === [] && $declaredTotal > 0) {
            throw new EdiParseException(
                'Soubor nevypadá jako platný EDI deník – nepodařilo se z něj načíst žádné spojení.',
                $ignored,
            );
        }

        // Platná + odmítnutá (neplatný lokátor) + ignorovaná (ERROR) spojení
        // musí dohromady dát deklarovaný počet; jinak v deníku něco chybí.
        if ($declaredTotal !== count($qsos) + count($lineErrors) + count($ignored)) {
            throw new EdiParseException(
                sprintf(
                    'Nesoulad počtu QSO: deklarováno %d, naparsováno %d, odmítnuto %d, ignorováno %d.',
                    $declaredTotal,
                    count($qsos),
                    count($lineErrors),
                    count($ignored),
                ),
                $ignored,
            );
        }

        return new EdiLog(
            header: new EdiHeader($fields),
            qsos: $qsos,
            rawSource: $raw,
            declaredTotal: $declaredTotal,
            lineErrors: $lineErrors,
            ignoredLines: $ignored,
        );
    }
}

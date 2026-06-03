<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Exceptions\EdiParseException;

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
            $converted = iconv('Windows-1250', 'UTF-8//IGNORE', $content);
            if ($converted !== false) {
                $content = $converted;
            }
        }

        $section = '';
        $fields = [];
        $qsos = [];
        $ignored = [];
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
                if (preg_match(self::QSO_PATTERN, $upper, $m)) {
                    $qsos[] = EdiQso::fromMatch($m);
                } elseif ($buf !== '') {
                    // Neparsovatelný řádek (značka „ERROR", prázdná povinná pole,
                    // neplatný lokátor…) – přeskočíme a importujeme zbytek deníku.
                    // Nezapočítává se do platných QSO.
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

        // Žádné platné spojení, ač deník nějaká deklaruje → soubor nemá platnou
        // strukturu EDI (např. odeslaný .txt místo .edi) → odmítnout.
        if ($qsos === [] && $declaredTotal > 0) {
            throw new EdiParseException(
                'Soubor nevypadá jako platný EDI deník – nepodařilo se z něj načíst žádné spojení.',
                $ignored,
            );
        }

        // Platná + ignorovaná spojení musí dohromady dát deklarovaný počet;
        // jinak v deníku něco chybí/přebývá (nesedí počet řádků).
        if ($declaredTotal !== count($qsos) + count($ignored)) {
            throw new EdiParseException(
                sprintf(
                    'Nesoulad počtu QSO: deklarováno %d, naparsováno %d, ignorováno %d.',
                    $declaredTotal,
                    count($qsos),
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
            ignoredLines: $ignored,
        );
    }
}

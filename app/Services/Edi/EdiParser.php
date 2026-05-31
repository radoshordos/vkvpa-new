<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Exceptions\EdiParseException;

/**
 * Parser EDI deníku (formát REG1TEST). Čistá, testovatelná služba bez DB a výstupu.
 *
 * Zachovává chování legacy read_edi.php (stejný stavový automat i regex QSO),
 * jen místo echo/exit vrací strukturovaná data, resp. vyhazuje výjimku.
 */
final class EdiParser
{
    /**
     * Regex jednoho QSO řádku – 15 skupin přesně dle původního read_edi.php.
     */
    private const string QSO_PATTERN =
        '/^([0-9]+);([0-9]+);([0-9A-Z\/]+);([0-9]*);([0-9]+[AS]?);([0-9]+);'
        . '([0-9]+[AS]?);([0-9]+);([0-9]*);([A-Z]{2}[0-9]{2}[A-Z]{2});([0-9]+);'
        . '([A-Z0-9]*);([A-Z0-9]*);([A-Z0-9]*);([A-Z0-9]*)/';

    /**
     * Naparsuje obsah EDI souboru.
     *
     * @throws EdiParseException při nesouladu deklarovaného a skutečného počtu QSO
     */
    public function parse(string $content): EdiLog
    {
        $section = '';
        $fields = [];
        $qsos = [];
        $errors = [];
        $declaredTotal = 0;
        $raw = '';

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            // Odstranění BOM a oříznutí (shodně s legacy).
            $buf = trim(str_replace("\xEF\xBB\xBF", '', $line));
            $raw .= $buf . "\n";

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
                    $errors[] = $buf;
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

        if ($declaredTotal !== count($qsos)) {
            throw new EdiParseException(
                sprintf(
                    'Nesoulad počtu QSO: deklarováno %d, naparsováno %d.',
                    $declaredTotal,
                    count($qsos),
                ),
                $errors,
            );
        }

        return new EdiLog(
            header: new EdiHeader($fields),
            qsos: $qsos,
            rawSource: $raw,
            declaredTotal: $declaredTotal,
            lineErrors: $errors,
        );
    }
}

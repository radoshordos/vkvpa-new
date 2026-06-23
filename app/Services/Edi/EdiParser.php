<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Enums\QsoCountStatus;
use App\Exceptions\EdiParseException;
use Illuminate\Support\Facades\Log;

/**
 * Parser EDI deníku (formát REG1TEST). Čistá, testovatelná služba bez DB a výstupu.
 */
final class EdiParser
{
    /**
     * Počet oddělovačů (středníků) v jednom QSO záznamu. Formát REG1TEST má
     * 15 polí, tedy přesně 14 středníků mezi nimi (viz VHF Handbook, sekce
     * „QSO record definition": 61 znaků dat + 14 separátorů = max. 75 znaků).
     */
    private const int QSO_FIELD_SEPARATORS = 14;

    /**
     * Regex jednoho QSO řádku – 15 skupin. Volitelná (prázdná) jsou pole, jejichž
     * chybění z QSO nedělá chybu formátu, jen ho případně zneplatní až při
     * vyhodnocení: čas, odeslaný/přijatý RST, přijaté pořadové číslo, přijatý
     * lokátor a body za QSO. Spojení bez přijatého RST nebo čísla se naimportuje,
     * ale ve skóre se nezapočítá ({@see QsoCountStatus::IncompleteExchange}).
     *
     * Report (odeslaný/přijatý) zachytáváme jako číslice s volitelným jedním
     * koncovým písmenem ([A-Z]) – záměrně i písmeno mimo povolenou trojici, ať
     * se spojení naimportuje a neplatný tónový znak jen vyvolá varování
     * ({@see RstPropagation}, {@see EdiValidationReport}), místo aby se řádek
     * tiše zahodil.
     */
    private const string QSO_PATTERN =
        '/^([0-9]+);([0-9]*);([0-9A-Z\/]+);([0-9]*);([0-9]*[A-Z]?);([0-9]+);'
        .'([0-9]*[A-Z]?);([0-9]*);([0-9]*);([A-Z]{2}[0-9]{2}[A-Z]{2})?;([0-9]*);'
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
        $dateTimeErrors = [];
        $separatorErrors = [];
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
                } elseif (preg_match('/^[0-9]/', $buf) === 1) {
                    // Řádek vypadá jako QSO záznam (začíná datem). Validujeme ho po
                    // krocích od nejzávažnější chyby, ať závodník dostane konkrétní
                    // důvod, ne jen obecné „nevypadá jako platný EDI".
                    $f = explode(';', $upper);

                    if (count($f) - 1 !== self::QSO_FIELD_SEPARATORS) {
                        // Chybějící/přebytečné pole – záznam nemá 15 polí.
                        $separatorErrors[] = sprintf(
                            'QSO řádek „%s" má %d oddělovačů (středníků), ale formát REG1TEST '
                            .'vyžaduje přesně %d (15 polí oddělených středníkem).',
                            $buf,
                            count($f) - 1,
                            self::QSO_FIELD_SEPARATORS,
                        );
                    } elseif (self::dateTimeFormatError($f[0], $f[1])) {
                        // Datum/čas je VYPLNĚNÝ, ale ve špatném formátu (např. 9místné
                        // datum, 3místný čas) – jednoznačná chyba. Prázdné pole sem
                        // nespadá: to je neúplný záznam, zneplatní se až při vyhodnocení.
                        $dateTimeErrors[] = 'QSO s '.$f[2].': datum „'.$f[0].'" / čas „'.$f[1]
                            .'" není ve formátu RRMMDD / HHMM (UTC).';
                    } elseif (preg_match(self::QSO_PATTERN, $upper, $m)) {
                        // Je-li lokátor vyplněn, musí být platný Maidenhead (A–R,
                        // číslice, subčtverec A–X); jinak QSO odmítneme. Prázdný
                        // lokátor projde – spojení se naimportuje, ale nezapočítá.
                        if ($m[10] !== '' && preg_match('/^[A-R]{2}[0-9]{2}([A-X]{2})?$/', $m[10]) !== 1) {
                            $lineErrors[] = 'QSO s '.$m[3].' odmítnuto: lokátor „'.$m[10]
                                .'" není platný Maidenhead (první 2 písmena musí být A–R, subčtverec A–X).';
                        } else {
                            $qsos[] = EdiQso::fromMatch($m);
                        }
                    } else {
                        // 15 polí i formálně správné datum/čas, ale řádek přesto
                        // nevyhověl formátu (např. nestandardní znaky) – přeskočíme.
                        $ignored[] = $buf;
                    }
                } elseif ($buf !== '') {
                    // Řádek nezačíná datem (úplně nevalidní soubor apod.) – přeskočíme.
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

        // Nesprávný počet oddělovačů → záznam nemá 15 polí. Strukturální chyba,
        // import odmítáme s výpisem konkrétních vadných řádků.
        if ($separatorErrors !== []) {
            throw new EdiParseException(
                'Některý QSO záznam nemá správný počet polí. Každé spojení musí mít přesně '
                .'15 polí oddělených 14 středníky (;) dle formátu REG1TEST '
                .'(Date;Time;Call;Mode;SentRST;SentNr;RcvdRST;RcvdNr;RcvdExch;RcvdWWL;'
                .'QSO-Points;NewExch;NewWWL;NewDXCC;Duplicate). Opravte deník ve svém '
                .'závodním programu a nahrajte jej znovu.',
                $separatorErrors,
            );
        }

        // Datum/čas v nesprávném formátu → import odmítáme a vysvětlíme závodníkovi
        // očekávaný tvar, ať deník opraví ve svém závodním programu.
        if ($dateTimeErrors !== []) {
            throw new EdiParseException(
                'Datum nebo čas spojení není ve správném formátu. EDI deník musí mít u každého QSO '
                .'datum ve tvaru RRMMDD (6 číslic, např. 240907 = 7. 9. 2024) a čas v UTC ve tvaru '
                .'HHMM (4 číslice, např. 1445). Opravte deník ve svém závodním programu (častou '
                .'příčinou je čtyřmístný rok ve tvaru RRRRMMDD) a nahrajte jej znovu.',
                $dateTimeErrors,
            );
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

    /**
     * Vrací true, je-li datum nebo čas VYPLNĚNÝ, ale ve špatném formátu – takový
     * deník odmítáme. Prázdné pole chybou formátu není (jde o neúplný záznam,
     * který se zneplatní až při vyhodnocení), proto se sem nepočítá.
     */
    private static function dateTimeFormatError(string $date, string $time): bool
    {
        $dateBad = $date !== '' && preg_match('/^[0-9]{6}$/', $date) !== 1;
        $timeBad = $time !== '' && preg_match('/^[0-9]{4}$/', $time) !== 1;
        if ($dateBad || $timeBad) {
            return true;
        }

        // Obě pole vyplněná a správné délky → ověřit i platnost kalendáře.
        if ($date !== '' && $time !== '') {
            return ! self::hasValidDateTime($date, $time);
        }

        return false;
    }

    /**
     * Datum musí být RRMMDD (6 číslic), čas HHMM v UTC (4 číslice) a dohromady
     * musí dávat existující kalendářní datum a čas. Přetečení (13. měsíc, 25.
     * hodina) {@see \DateTimeImmutable::createFromFormat()} tiše „převalí", proto
     * navíc kontrolujeme getLastErrors(). Shodný formát používá EdiQso::combineDateTime().
     */
    private static function hasValidDateTime(string $date, string $time): bool
    {
        if (preg_match('/^[0-9]{6}$/', $date) !== 1 || preg_match('/^[0-9]{4}$/', $time) !== 1) {
            return false;
        }

        \DateTimeImmutable::createFromFormat('!ymdHi', $date.$time, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }
}

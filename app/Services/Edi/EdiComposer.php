<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Support\Maidenhead;

/**
 * Skladač EDI deníku (formát REG1TEST) – opak {@see EdiParser}. Z ručně
 * zadané hlavičky a seznamu spojení složí platný `.edi` text, který projde
 * zpětnou validací parserem.
 *
 * Slouží ručnímu generátoru deníku (App\Livewire\EdiGenerator); je to
 * čistá, testovatelná služba bez DB a výstupu (zrcadlo {@see EdiReducer}).
 *
 * Klíče hlavičky: tname, tdate (Y-m-d), pcall, pwwlo, psect, pband, rname,
 * rphon, rhbbs, spowe, stxeq, sante, remarks. Klíče spojení: date, time,
 * call, mode, rst_s, nr_s, rst_r, nr_r, wwl.
 */
final class EdiComposer
{
    /**
     * Pásma (PBand) nabízená v generátoru – hodnoty přijímá {@see CategoryResolver}.
     *
     * @var list<string>
     */
    public const array BANDS = [
        '144 MHz', '432 MHz', '1,3 GHz', '2,3 GHz', '3,4 GHz',
        '5,7 GHz', '10 GHz', '24 GHz', '47 GHz', '76 GHz', '122 GHz',
    ];

    /**
     * Složí EDI text z hlavičky a spojení. Body za spojení (sloupec 11) se
     * dopočítají z lokátorů shodně se ScoringService (sloupec `QSO-Points`
     * z deníku se ignoruje). Nekompletní řádky (bez volačky nebo lokátoru)
     * se vynechají a do `[QSORecords;N]` se nezapočítají.
     *
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $qsos
     */
    public function compose(array $header, array $qsos): string
    {
        $homeSq = Maidenhead::bigSquare($this->val($header, 'pwwlo'));
        // Datum spojení se primárně bere z řádku; chybí-li, doplní se z dne závodu.
        $defaultDate = $this->qsoDate($this->val($header, 'tdate'));

        $lines = [];
        $counter = 0;
        foreach ($qsos as $qso) {
            $call = strtoupper(trim($this->val($qso, 'call')));
            $wwl = strtoupper(trim($this->val($qso, 'wwl')));
            if ($call === '' || $wwl === '') {
                continue;
            }

            $counter++;
            $lines[] = $this->qsoLine($qso, $call, $wwl, $homeSq, $counter, $defaultDate);
        }

        $out = [
            '[REG1TEST;1]',
            'TName='.trim($this->val($header, 'tname')),
            'TDate='.$this->tdate($this->val($header, 'tdate')),
            'PCall='.strtoupper(trim($this->val($header, 'pcall'))),
            'PWWLo='.strtoupper(trim($this->val($header, 'pwwlo'))),
            'PSect='.trim($this->val($header, 'psect')),
            'PBand='.trim($this->val($header, 'pband')),
            'RName='.trim($this->val($header, 'rname')),
            'RPhon='.trim($this->val($header, 'rphon')),
            'RHBBS='.trim($this->val($header, 'rhbbs')),
            'SPowe='.$this->power($this->val($header, 'spowe')),
            'STXEq='.trim($this->val($header, 'stxeq')),
            'SAnte='.trim($this->val($header, 'sante')),
            '[Remarks]',
            trim($this->val($header, 'remarks')),
            '[QSORecords;'.count($lines).']',
            ...$lines,
            '[END;]',
        ];

        return implode("\r\n", $out)."\r\n";
    }

    /**
     * Jeden QSO řádek (15 polí dle REG1TEST).
     *
     * @param  array<string, mixed>  $qso
     */
    private function qsoLine(array $qso, string $call, string $wwl, string $homeSq, int $counter, string $defaultDate): string
    {
        $date = $this->qsoDate($this->val($qso, 'date')) ?: $defaultDate;
        $time = $this->digits($this->val($qso, 'time'), 4);
        $mode = $this->digits($this->val($qso, 'mode'), 0) ?: '0';
        $rstS = $this->rst($this->val($qso, 'rst_s'));
        $rstR = $this->rst($this->val($qso, 'rst_r'));
        $nrS = $this->serial($this->val($qso, 'nr_s'), $counter);
        $nrR = $this->serial($this->val($qso, 'nr_r'), $counter);
        $points = Maidenhead::qsoPoints($homeSq, Maidenhead::bigSquare($wwl));

        // Date;Time;Call;Mode;SentRST;SentNo;RecRST;RecNo;RecExch;WWL;Points;NewExch;NewWWL;NewDXCC;Dup
        return implode(';', [$date, $time, $call, $mode, $rstS, $nrS, $rstR, $nrR, '', $wwl, (string) $points, '', '', '', '']);
    }

    /** Pořadové číslo zleva doplněné nulami; prázdné nahradí pořadím řádku. */
    private function serial(string $value, int $counter): string
    {
        $digits = $this->digits($value, 0) ?: (string) $counter;

        return str_pad($digits, 3, '0', STR_PAD_LEFT);
    }

    /** TDate jako jednodenní rozsah „YYYYMMDD;YYYYMMDD" (z data ve formátu Y-m-d). */
    private function tdate(string $ymd): string
    {
        $compact = $this->digits(str_replace('-', '', $ymd), 8);

        return $compact === '' ? '' : $compact.';'.$compact;
    }

    /** Datum QSO „YYMMDD" – z ISO Y-m-d, jinak z již zadaných číslic. */
    private function qsoDate(string $value): string
    {
        $d = $this->digits(str_replace('-', '', $value), 8);

        return strlen($d) === 8 ? substr($d, 2) : $this->digits($value, 6);
    }

    /** Výkon „NNNW" (z čísla); prázdné zůstává prázdné. */
    private function power(string $value): string
    {
        $w = $this->digits($value, 0);

        return $w === '' ? '' : $w.'W';
    }

    /** RST jako číslice (případně s příponou A/S); default „59". */
    private function rst(string $value): string
    {
        $v = strtoupper(trim($value));
        if (preg_match('/^[0-9]+[AS]?$/', $v) === 1) {
            return $v;
        }

        return '59';
    }

    /** Vrátí jen číslice ze vstupu, volitelně oříznuté na max délku ($max>0). */
    private function digits(string $value, int $max): string
    {
        $d = preg_replace('/[^0-9]/', '', $value) ?? '';

        return $max > 0 ? substr($d, 0, $max) : $d;
    }

    /**
     * Skalární hodnotu z pole vrátí jako řetězec; cokoliv jiného jako prázdno.
     *
     * @param  array<string, mixed>  $arr
     */
    private function val(array $arr, string $key): string
    {
        $value = $arr[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }
}

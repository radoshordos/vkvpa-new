<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Actions\ImportEdiAction;
use App\Enums\Severity;
use App\Support\Finding;

/**
 * Souhrn nálezů kontroly kvality EDI deníku po importu (nefatální upozornění).
 *
 * Na rozdíl od výjimek v {@see ImportEdiAction} tyto nálezy import
 * nezastaví – jen upozorní závodníka na věci, které snižují bodový zisk nebo
 * mohou značit chybu v deníku (duplicity, vadné lokátory, QSO mimo závod).
 */
final readonly class EdiValidationReport
{
    /**
     * @param  array<string, int>  $duplicateCalls   značka → počet výskytů (jen >1)
     * @param  list<string>        $invalidLocators   ukázka „ZNAČKA: WWL" s vadným lokátorem
     * @param  list<string>        $lineErrors        QSO odmítnutá parserem (neplatný Maidenhead)
     * @param  ?string             $invalidHomeLocator neplatný PWWLo (null = v pořádku)
     */
    public function __construct(
        public array $duplicateCalls,
        public array $invalidLocators,
        public int $emptyLocators,
        public int $outOfWindow,
        public int $wrongDate,
        public int $declaredTotal,
        public int $parsedCount,
        public array $lineErrors,
        public int $ignoredLines,
        public bool $emptyPCall = false,
        public ?string $invalidHomeLocator = null,
    ) {}

    public function hasWarnings(): bool
    {
        return $this->messages() !== [];
    }

    /**
     * Lidsky čitelná upozornění (cs) pro zobrazení závodníkovi.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        $m = [];

        // ── Problémy v hlavičce (nejzávažnější – vliv na celý deník) ─────────

        if ($this->emptyPCall) {
            $m[] = 'Hlavička deníku neobsahuje volací značku (PCall) – deník nelze přiřadit závodníkovi. Zkontroluj pole PCall v EDI souboru.';
        }

        if ($this->invalidHomeLocator !== null) {
            $loc = $this->invalidHomeLocator === '' ? '(prázdné)' : '„'.$this->invalidHomeLocator.'"';
            $m[] = 'Domácí lokátor '.$loc.' v poli PWWLo není platný Maidenhead formát – výsledné skóre bude 0, protože vzdálenosti ani body nelze spočítat.';
        }

        // ── QSO odmítnutá parserem (neplatný lokátor) ────────────────────────

        foreach ($this->lineErrors as $err) {
            $m[] = $err;
        }

        // ── Ostatní QSO-úroveň ───────────────────────────────────────────────

        if ($this->duplicateCalls !== []) {
            $list = [];
            foreach (array_slice($this->duplicateCalls, 0, 8, true) as $call => $n) {
                $list[] = $call.' ('.$n.'×)';
            }
            $more = count($this->duplicateCalls) > 8 ? ' …' : '';
            $m[] = 'Duplicitní spojení (stanice navázána víckrát): '.implode(', ', $list).$more.'. Duplicity se bodují 0.';
        }

        if ($this->invalidLocators !== []) {
            $more = count($this->invalidLocators) >= 8 ? ' …' : '';
            $m[] = 'Neplatný WWL lokátor u spojení: '.implode(', ', $this->invalidLocators).$more.'. Zkontroluj deník – chybný lokátor zkresluje body i násobiče.';
        }

        if ($this->emptyLocators > 0) {
            $m[] = $this->emptyLocators.' spojení bez WWL lokátoru – tato spojení se nezapočítávají.';
        }

        if ($this->outOfWindow > 0) {
            $m[] = $this->outOfWindow.' spojení mimo závodní okno – nezapočítávají se.';
        }

        if ($this->wrongDate > 0) {
            $m[] = $this->wrongDate.' spojení mimo den závodu – nezapočítávají se.';
        }

        if ($this->declaredTotal > 0 && $this->declaredTotal !== $this->parsedCount) {
            $m[] = 'Hlavička deklaruje '.$this->declaredTotal.' QSO, ale v deníku jich je '.$this->parsedCount.'.';
        }

        if ($this->ignoredLines > 0) {
            $m[] = $this->ignoredLines.' řádků označených chybou (ERROR) v logu bylo přeskočeno.';
        }

        return $m;
    }

    /**
     * Nálezy s klasifikací závažnosti pro administrátorský pohled.
     *
     * @return list<Finding>
     */
    public function findings(): array
    {
        $m = [];

        // ── Problémy v hlavičce (nejzávažnější – vliv na celý deník) ─────────

        if ($this->emptyPCall) {
            $m[] = new Finding(
                Severity::Fatal,
                'Hlavička deníku neobsahuje volací značku (PCall) – deník nelze přiřadit závodníkovi. Zkontroluj pole PCall v EDI souboru.',
            );
        }

        if ($this->invalidHomeLocator !== null) {
            $loc = $this->invalidHomeLocator === '' ? '(prázdné)' : '„'.$this->invalidHomeLocator.'"';
            $m[] = new Finding(
                Severity::Fatal,
                'Domácí lokátor '.$loc.' v poli PWWLo není platný Maidenhead formát – výsledné skóre bude 0, protože vzdálenosti ani body nelze spočítat.',
            );
        }

        // ── QSO odmítnutá parserem (neplatný lokátor) ────────────────────────

        foreach ($this->lineErrors as $err) {
            $m[] = new Finding(Severity::Warning, $err);
        }

        // ── Ostatní QSO-úroveň ───────────────────────────────────────────────

        if ($this->duplicateCalls !== []) {
            $list = [];
            foreach (array_slice($this->duplicateCalls, 0, 8, true) as $call => $n) {
                $list[] = $call.' ('.$n.'×)';
            }
            $more = count($this->duplicateCalls) > 8 ? ' …' : '';
            $m[] = new Finding(
                Severity::Warning,
                'Duplicitní spojení (stanice navázána víckrát): '.implode(', ', $list).$more.'. Duplicity se bodují 0.',
            );
        }

        if ($this->invalidLocators !== []) {
            $more = count($this->invalidLocators) >= 8 ? ' …' : '';
            $m[] = new Finding(
                Severity::Warning,
                'Neplatný WWL lokátor u spojení: '.implode(', ', $this->invalidLocators).$more.'. Zkontroluj deník – chybný lokátor zkresluje body i násobiče.',
            );
        }

        if ($this->emptyLocators > 0) {
            $m[] = new Finding(
                Severity::Warning,
                $this->emptyLocators.' spojení bez WWL lokátoru – tato spojení se nezapočítávají.',
            );
        }

        if ($this->outOfWindow > 0) {
            $m[] = new Finding(
                Severity::Info,
                $this->outOfWindow.' spojení mimo závodní okno – nezapočítávají se.',
            );
        }

        if ($this->wrongDate > 0) {
            $m[] = new Finding(
                Severity::Info,
                $this->wrongDate.' spojení mimo den závodu – nezapočítávají se.',
            );
        }

        if ($this->declaredTotal > 0 && $this->declaredTotal !== $this->parsedCount) {
            $m[] = new Finding(
                Severity::Warning,
                'Hlavička deklaruje '.$this->declaredTotal.' QSO, ale v deníku jich je '.$this->parsedCount.'.',
            );
        }

        if ($this->ignoredLines > 0) {
            $m[] = new Finding(
                Severity::Info,
                $this->ignoredLines.' řádků označených chybou (ERROR) v logu bylo přeskočeno.',
            );
        }

        return $m;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Edi;

/**
 * Hlavička EDI deníku – mapa klíč=hodnota z REG1TEST sekce + typované přístupy.
 */
final readonly class EdiHeader
{
    /**
     * @param  array<string,string>  $fields  syrová mapa klíč=hodnota
     */
    public function __construct(public array $fields) {}

    public function get(string $key, string $default = ''): string
    {
        return $this->fields[$key] ?? $default;
    }

    public function pCall(): string
    {
        return $this->get('PCall');
    }

    public function pWWLo(): string
    {
        return $this->get('PWWLo');
    }

    public function pSect(): string
    {
        return $this->get('PSect');
    }

    public function pBand(): string
    {
        return $this->get('PBand');
    }

    public function rName(): string
    {
        return $this->get('RName');
    }

    public function rPhon(): string
    {
        return $this->get('RPhon');
    }

    /** E-mail účastníka – dle REG1TEST formátu uložen v poli RHBBS. */
    public function rHBBS(): string
    {
        return $this->get('RHBBS');
    }

    /**
     * E-mail pro kontakt – primárně RHBBS, záloha pole REmai.
     * REG1TEST ukládá e-mail do RHBBS; starší deníky používají REmai.
     */
    public function rEmail(): string
    {
        return $this->rHBBS() !== '' ? $this->rHBBS() : $this->get('REmai');
    }

    public function tDate(): string
    {
        return $this->get('TDate');
    }

    public function sTXEq(): string
    {
        return $this->get('STXEq');
    }

    public function sAnte(): string
    {
        return $this->get('SAnte');
    }

    /** Výkon v W jako celé číslo (např. „800W" → 800). */
    public function sPowe(): int
    {
        return (int) $this->get('SPowe');
    }

    /** QRP = výkon 1–5 W. */
    public function isQrp(): bool
    {
        $p = $this->sPowe();

        return $p > 0 && $p <= 5;
    }
}

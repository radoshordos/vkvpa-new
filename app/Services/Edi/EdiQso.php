<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Console\Commands\BackfillEdilineQsoAt;

/**
 * Jedno spojení (QSO) z EDI deníku – 15 polí přesně dle formátu REG1TEST.
 * Hodnoty jsou uchovány tak, jak byly naparsovány (řetězce); přetypování
 * na cílové DB typy řeší EdiImportService.
 */
final readonly class EdiQso
{
    public function __construct(
        public string $date,
        public string $time,
        public string $callSign,
        public string $modeCode,
        public string $sentRst,
        public string $sentQsoNumber,
        public string $receivedRst,
        public string $receivedQsoNumber,
        public string $receivedExchange,
        public string $receivedWwl,
        public string $qsoPoints,
        public string $newExchange,
        public string $newWwl,
        public string $newDxcc,
        public string $duplicate,
    ) {}

    /**
     * Sloučené datum+čas QSO jako UTC DATETIME 'Y-m-d H:i:s', nebo null když
     * pole chybí / nedávají platné datum. Závodní časy jsou vždy UTC (shodně
     * s ContestWindow/ScoringService); roky 00–68 → 20xx, 69–99 → 19xx (pivot
     * PHP formátu 'y').
     */
    public function qsoAt(): ?string
    {
        return self::combineDateTime($this->date, $this->time);
    }

    /**
     * Složí EDI datum ('YYMMDD') a čas ('HHMM') do UTC DATETIME 'Y-m-d H:i:s'.
     * Sdíleno importem ({@see EdiImportService}) i zpětným doplněním legacy
     * deníků ({@see BackfillEdilineQsoAt}).
     */
    public static function combineDateTime(string $date, string $time): ?string
    {
        if ($date === '' || $time === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('!ymdHi', $date.$time, new \DateTimeZone('UTC'));

        return $dt === false ? null : $dt->format('Y-m-d H:i:s');
    }

    /**
     * Vytvoří QSO z 15 zachycených skupin regexu (index 1..15 z preg_match).
     *
     * @param  array<int,string>  $m
     */
    public static function fromMatch(array $m): self
    {
        return new self(
            date: $m[1],
            time: $m[2],
            callSign: $m[3],
            modeCode: $m[4],
            sentRst: $m[5],
            sentQsoNumber: $m[6],
            receivedRst: $m[7],
            receivedQsoNumber: $m[8],
            receivedExchange: $m[9],
            receivedWwl: $m[10],
            qsoPoints: $m[11],
            newExchange: $m[12],
            newWwl: $m[13],
            newDxcc: $m[14],
            duplicate: $m[15],
        );
    }
}

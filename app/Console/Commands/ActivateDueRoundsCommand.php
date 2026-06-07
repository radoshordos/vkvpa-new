<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VkvpaKola;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Aktivuje závodní kola, jejichž čas zahájení (datum_konani 08:00 UTC) nastal.
 * Spouštěno každou hodinu naplánovanou úlohou.
 */
final class ActivateDueRoundsCommand extends Command
{
    protected $signature = 'kola:activate-due';

    protected $description = 'Aktivuje kola, jejichž čas zahájení (08:00 UTC) nastal (aktivni = true).';

    public function handle(): int
    {
        $nowUtc = Carbon::now('UTC');

        // Kandidáti: neaktivní kola, jejichž den závodu nastal a uzávěrka ještě neuplynula.
        $candidates = VkvpaKola::query()
            ->where('aktivni', false)
            ->whereNotNull('datum_uzaverky')
            ->where('datum_uzaverky', '>', $nowUtc)
            ->where('datum_konani', '<=', $nowUtc->toDateString())
            ->get();

        $pocet = 0;

        foreach ($candidates as $kolo) {
            // Aktivovat až od 08:00:00 UTC v den závodu.
            $startUtc = Carbon::parse(
                $kolo->datum_konani->format('Y-m-d').' 08:00:00',
                'UTC',
            );

            if ($nowUtc->greaterThanOrEqualTo($startUtc)) {
                $kolo->update(['aktivni' => true]);
                $pocet++;
            }
        }

        if ($pocet > 0) {
            Log::info('schedule.kola.activate_due', ['pocet' => $pocet]);
            $this->info("Aktivováno {$pocet} kol.");
        }

        return self::SUCCESS;
    }
}

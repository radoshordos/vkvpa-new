<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VkvpaKola;
use App\Support\ContestCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Zajistí existenci závodních kol pro nadcházející měsíce.
 * Spouštěno každý den naplánovanou úlohou.
 */
final class EnsureUpcomingRoundsCommand extends Command
{
    protected $signature = 'kola:ensure-upcoming {months=3 : Počet měsíců dopředu}';

    protected $description = 'Vytvoří závodní kola pro nadcházející měsíce, pokud ještě neexistují.';

    public function handle(): int
    {
        $months = max(1, (int) $this->argument('months'));
        $created = 0;
        $now = CarbonImmutable::now('UTC');

        for ($i = 0; $i < $months; $i++) {
            $target = $now->addMonths($i);
            $year = (int) $target->format('Y');
            $month = (int) $target->format('n');

            if ($this->roundExists($year, $month)) {
                continue;
            }

            $start = ContestCalendar::roundStart($year, $month);
            $deadline = ContestCalendar::uploadDeadline($start);

            VkvpaKola::create([
                'nazev' => ContestCalendar::roundName($year, $month),
                'datum_konani' => $start->toDateTimeString(),
                'datum_uzaverky' => $deadline->toDateTimeString(),
                'poznamka' => '',
            ]);

            $this->info(sprintf(
                'Vytvořeno: %s (start %s – uzávěrka %s)',
                ContestCalendar::roundName($year, $month),
                $start->toDateTimeString(),
                $deadline->toDateString(),
            ));

            $created++;
        }

        if ($created > 0) {
            Log::info('schedule.kola.ensure_upcoming', ['created' => $created]);
        }

        return self::SUCCESS;
    }

    private function roundExists(int $year, int $month): bool
    {
        return VkvpaKola::query()
            ->whereYear('datum_konani', $year)
            ->whereMonth('datum_konani', $month)
            ->exists();
    }
}

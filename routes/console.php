<?php

declare(strict_types=1);

use App\Console\Commands\EnsureUpcomingRoundsCommand;
use App\Console\Commands\FinalizeEvaluatedRoundsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Stavy nadcházející / probíhá / příjem / zpracování se odvozují čistě z času –
// žádná naplánovaná aktivace/deaktivace není potřeba. Přechod do Vyhodnocené
// závisí i na převzetí záznamů, proto ho zajišťuje denní příkaz níže.

// Vytvoří závodní kola pro nadcházející 3 měsíce, pokud ještě neexistují.
// Spouští se každý den; ručně: `php artisan kola:ensure-upcoming`.
Schedule::command(EnsureUpcomingRoundsCommand::class)
    ->daily()
    ->withoutOverlapping()
    ->skip(fn (): bool => app()->runningUnitTests());

// Vyhodnotí kola po uzávěrce (vše převzato nebo uplynulo 20 dní od uzávěrky).
// Spouští se každý den; ručně: `php artisan kola:finalize-evaluated`.
Schedule::command(FinalizeEvaluatedRoundsCommand::class)
    ->daily()
    ->withoutOverlapping()
    ->skip(fn (): bool => app()->runningUnitTests());

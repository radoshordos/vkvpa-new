<?php

declare(strict_types=1);

use App\Console\Commands\EnsureUpcomingRoundsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Stav kola (nadcházející / probíhá / příjem / uzavřené) se odvozuje čistě
// z času – žádná naplánovaná aktivace/deaktivace není potřeba.

// Vytvoří závodní kola pro nadcházející 3 měsíce, pokud ještě neexistují.
// Spouští se každý den; ručně: `php artisan kola:ensure-upcoming`.
Schedule::command(EnsureUpcomingRoundsCommand::class)
    ->daily()
    ->withoutOverlapping()
    ->skip(fn (): bool => app()->runningUnitTests());

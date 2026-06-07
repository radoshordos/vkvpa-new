<?php

declare(strict_types=1);

use App\Console\Commands\ActivateDueRoundsCommand;
use App\Console\Commands\DeactivateExpiredRoundsCommand;
use App\Console\Commands\EnsureUpcomingRoundsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Po uplynutí uzávěrky kolo automaticky deaktivovat (přestane přijímat hlášení).
// Spouští se každou hodinu; ručně lze přes `php artisan kola:deactivate-expired`.
Schedule::command(DeactivateExpiredRoundsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->skip(fn (): bool => app()->runningUnitTests());

// Aktivuje kola, jejichž čas zahájení (třetí neděle 08:00 UTC) nastal.
// Spouští se každou hodinu; ručně: `php artisan kola:activate-due`.
Schedule::command(ActivateDueRoundsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->skip(fn (): bool => app()->runningUnitTests());

// Vytvoří závodní kola pro nadcházející 3 měsíce, pokud ještě neexistují.
// Spouští se každý den; ručně: `php artisan kola:ensure-upcoming`.
Schedule::command(EnsureUpcomingRoundsCommand::class)
    ->daily()
    ->withoutOverlapping()
    ->skip(fn (): bool => app()->runningUnitTests());

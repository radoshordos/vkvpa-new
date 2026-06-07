<?php

declare(strict_types=1);

use App\Console\Commands\DeactivateExpiredRoundsCommand;
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

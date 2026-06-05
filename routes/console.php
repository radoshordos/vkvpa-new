<?php

declare(strict_types=1);

use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Po uplynutí uzávěrky kolo automaticky deaktivovat (přestane přijímat hlášení).
// Spouští se každou hodinu; ručně lze přes `php artisan schedule:run`.
Schedule::call(function (ScoringService $scoring): void {
    $pocet = $scoring->deactivateExpiredRounds();
    if ($pocet > 0) {
        Log::info('schedule.kola.deactivate_expired', ['pocet' => $pocet]);
    }
})->hourly()->name('kola:deactivate-expired')->withoutOverlapping();

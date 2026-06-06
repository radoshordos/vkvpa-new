<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ApiDocsController;
use App\Http\Controllers\Api\VysledkyApiController;
use Illuminate\Support\Facades\Route;

/*
 * Veřejné REST API VKV PA.
 * Rate limit: throttle:api (60/min per IP).
 * CORS: config/cors.php (GET, všechny originy).
 */

Route::get('/kola', [VysledkyApiController::class, 'kola'])->name('api.kola');

Route::prefix('vysledky')->name('api.vysledky.')->group(function (): void {
    Route::get('/rocni/{rok}', [VysledkyApiController::class, 'rocni'])
        ->where('rok', '\d{4}')
        ->name('rocni');

    Route::get('/{kolo}', [VysledkyApiController::class, 'kolo'])
        ->whereNumber('kolo')
        ->name('kolo');
});

Route::get('/docs', [ApiDocsController::class, 'index'])->name('api.docs');
Route::get('/docs/spec', [ApiDocsController::class, 'spec'])->name('api.docs.spec');

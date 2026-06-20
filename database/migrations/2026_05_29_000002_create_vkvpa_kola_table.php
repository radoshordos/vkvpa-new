<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_kola', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            // Start závodu je přímo v `datum_konani` (datetime); stav kola se
            // odvozuje čistě z času. Kolo se koná jednou za měsíc → na jeden
            // den nejvýš jedno kolo, proto unikát (párování deníků dle TDate).
            $table->dateTime('datum_konani');
            $table->dateTime('datum_uzaverky');
            $table->string('nazev', 250);
            $table->dateTime('vyhodnoceno')->nullable();
            $table->string('poznamka', 250);

            $table->unique('datum_konani', 'vkvpa_kola_datum_konani_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_kola');
    }
};

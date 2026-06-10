<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historická data (import staré DB) mají jednu značku ve více
        // kategoriích (pásmech) v rámci jednoho kola. Unikátnost proto platí
        // až na úrovni kategorie; pravidlo „jedno hlášení na kolo a značku"
        // pro nová podání vynucuje aplikace (HlaseniController, ImportEdiAction).
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropUnique('vkvpa_data_kola_znacka_unique');
            $table->unique(['id_kola', 'znacka', 'id_kategorie'], 'vkvpa_data_kola_znacka_kat_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropUnique('vkvpa_data_kola_znacka_kat_unique');
            $table->unique(['id_kola', 'znacka'], 'vkvpa_data_kola_znacka_unique');
        });
    }
};

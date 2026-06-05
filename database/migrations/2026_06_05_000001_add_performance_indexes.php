<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Výkonnostní indexy pro časté dotazy:
 *
 * 1. vkvpa_data(id_kola, schvaleno) – filtrování výsledků kola (HlaseniController, VysledkyController)
 * 2. vkvpa_data(znacka, id_kola)    – kontrola duplicit při nahrávání deníku (EdiController)
 * 3. edihead(PCall)                 – vyhledávání hlavičky podle volací značky
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->index(['id_kola', 'schvaleno'], 'vkvpa_data_kola_schvaleno_idx');
            $table->index(['znacka', 'id_kola'], 'vkvpa_data_znacka_kola_idx');
        });

        Schema::table('edihead', function (Blueprint $table): void {
            $table->index('PCall', 'edihead_pcall_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropIndex('vkvpa_data_kola_schvaleno_idx');
            $table->dropIndex('vkvpa_data_znacka_kola_idx');
        });

        Schema::table('edihead', function (Blueprint $table): void {
            $table->dropIndex('edihead_pcall_idx');
        });
    }
};

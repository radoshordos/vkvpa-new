<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_data', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // Sloupce s explicitním DEFAULT jsou NOT NULL – aplikace do nich
            // NULL nikdy neukládá, prázdný řetězec/0/false vyjadřuje „nevyplněno".
            $table->integer('id', true);
            $table->integer('id_kola');
            $table->integer('id_kategorie')->nullable();
            $table->boolean('qrp')->default(false);
            $table->boolean('lp')->default(false);
            $table->string('znacka', 10);
            $table->string('locator', 6)->default('');
            $table->integer('pocet')->default(0);
            $table->integer('bodu_za_qso')->default(1);
            $table->integer('nasobice')->default(0);
            $table->integer('body')->default(0);
            $table->string('jmeno', 60)->default('');
            $table->string('mail', 250)->default('');
            $table->string('telefon', 20)->default('');
            $table->string('poznamka', 250)->default('');
            $table->string('soapbox', 250)->default('');
            $table->string('ip', 64)->default('');
            // NULL = hlášení bez deníku, jinak FK na edihead. Smazání deníku
            // odpojí jen vazbu – výsledkový řádek (body jsou denormalizované)
            // zůstává, stejně jako při mazání v adminu.
            $table->integer('edihead_id')->nullable();
            $table->integer('poradi')->default(0);
            $table->boolean('schvaleno')->default(false);
            $table->boolean('odeslano')->default(false);
            $table->string('session_id', 255)->default('');
            // DATETIME (ne TIMESTAMP) – nezávislé na session time_zone serveru.
            $table->dateTime('timestamp')->nullable()->useCurrent();

            // Historická data mají jednu značku ve více kategoriích (pásmech)
            // v rámci jednoho kola – unikátnost platí až na úrovni kategorie.
            $table->unique(['id_kola', 'znacka', 'id_kategorie'], 'vkvpa_data_kola_znacka_kat_unique');
            $table->index(['id_kola', 'znacka', 'schvaleno'], 'data1');
            $table->index('id_kategorie', 'vkvpa_data_id_kategorie_idx');
            $table->index(['id_kola', 'schvaleno'], 'vkvpa_data_kola_schvaleno_idx');
            $table->index('edihead_id', 'vkvpa_data_edihead_id_idx');
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('vkvpa_data', function (Blueprint $table): void {
            // Kola se nikdy nemažou → RESTRICT (CASCADE by při omylu smazal výsledky).
            $table->foreign('id_kola', 'vkvpa_data_id_kola_fk')
                ->references('id')->on('vkvpa_kola')
                ->restrictOnDelete();
            $table->foreign('id_kategorie', 'vkvpa_data_id_kategorie_fk')
                ->references('id')->on('vkvpa_kategorie')
                ->restrictOnDelete();
            $table->foreign('edihead_id', 'vkvpa_data_edihead_id_fk')
                ->references('id')->on('edihead')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_data');
    }
};

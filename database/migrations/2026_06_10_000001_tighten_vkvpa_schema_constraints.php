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
        // Kolo se koná vždy třetí neděli v měsíci → na jeden den připadá nejvýš
        // jedno kolo. Duplicitní kolo by rozbilo párování deníků (koloForTDate
        // hledá kolo podle data z TDate) – DB to musí odmítnout.
        Schema::table('vkvpa_kola', function (Blueprint $table): void {
            $table->unique('datum_konani', 'vkvpa_kola_datum_konani_unique');
        });

        // CategoryResolver mapuje sekci z EDI hlavičky na kategorii přes zkratku;
        // duplicitní zkratka by párování učinila nedeterministickým.
        Schema::table('vkvpa_kategorie', function (Blueprint $table): void {
            $table->unique('zkratka', 'vkvpa_kategorie_zkratka_unique');
        });

        // SQLite (testovací DB) neumí měnit cizí klíče přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Kola se nikdy nemažou (existují i bez záznamu o závodu) – CASCADE by
        // při omylu tiše smazal výsledky, deníky i diskusi. RESTRICT doménový
        // invariant vynucuje přímo v DB.
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropForeign('vkvpa_data_id_kola_fk');
            $table->foreign('id_kola', 'vkvpa_data_id_kola_fk')
                ->references('id')->on('vkvpa_kola')
                ->restrictOnDelete();
        });

        Schema::table('edihead', function (Blueprint $table): void {
            $table->dropForeign('edihead_id_kola_fk');
            $table->foreign('id_kola', 'edihead_id_kola_fk')
                ->references('id')->on('vkvpa_kola')
                ->restrictOnDelete();
        });

        Schema::table('diskuse', function (Blueprint $table): void {
            $table->dropForeign('diskuse_kolo_id_fk');
            $table->foreign('kolo_id', 'diskuse_kolo_id_fk')
                ->references('id')->on('vkvpa_kola')
                ->restrictOnDelete();
        });

        // Původní migrace omylem vytvořila DATETIME(1) – desetiny sekundy
        // u času vyhodnocení nemají význam.
        Schema::table('vkvpa_kola', function (Blueprint $table): void {
            $table->dateTime('vyhodnoceno')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_kola', function (Blueprint $table): void {
            $table->dropUnique('vkvpa_kola_datum_konani_unique');
        });

        Schema::table('vkvpa_kategorie', function (Blueprint $table): void {
            $table->dropUnique('vkvpa_kategorie_zkratka_unique');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropForeign('vkvpa_data_id_kola_fk');
            $table->foreign('id_kola', 'vkvpa_data_id_kola_fk')
                ->references('id')->on('vkvpa_kola')
                ->cascadeOnDelete();
        });

        Schema::table('edihead', function (Blueprint $table): void {
            $table->dropForeign('edihead_id_kola_fk');
            $table->foreign('id_kola', 'edihead_id_kola_fk')
                ->references('id')->on('vkvpa_kola')
                ->cascadeOnDelete();
        });

        Schema::table('diskuse', function (Blueprint $table): void {
            $table->dropForeign('diskuse_kolo_id_fk');
            $table->foreign('kolo_id', 'diskuse_kolo_id_fk')
                ->references('id')->on('vkvpa_kola')
                ->cascadeOnDelete();
        });

        Schema::table('vkvpa_kola', function (Blueprint $table): void {
            $table->dateTime('vyhodnoceno', 1)->nullable()->change();
        });
    }
};

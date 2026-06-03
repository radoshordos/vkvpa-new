<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opravy datové integrity:
 *
 * 1. vkvpa_prihlaseni.kod – přidán UNIQUE index; kódy jednorázového přihlášení
 *    musí být unikátní, jinak by kolize tokenů umožnila neoprávněné přihlášení.
 *
 * 2. vkvpa_data – odstraněno zbytečné nullable() ze sloupců, které mají
 *    explicitní DEFAULT a aplikace do nich nikdy NULL neukládá.
 *    nullable() + default(value) je redundantní a maskuje záměr – pokud je
 *    NULL neplatný stav, NOT NULL + DEFAULT ho zabrání na úrovni DB.
 *
 * 3. edilines – samostatný index na Time nahrazen kompozitním (IDS, Time).
 *    Všechny dotazy filtrují vždy podle obou sloupců najednou (Eloquent relace
 *    přidá WHERE IDS = ? automaticky), takže kompozit je efektivnější.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Unikátní přihlašovací kódy.
        Schema::table('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->unique('kod', 'vkvpa_prihlaseni_kod_unique');
        });

        // 2. Oprava nullable/default na vkvpa_data.
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            // Boolean příznaky – nikdy NULL, false je výchozí stav.
            $table->boolean('qrp')->default(false)->change();
            $table->boolean('lp')->default(false)->change();
            $table->boolean('EDI')->default(false)->change();
            $table->boolean('schvaleno')->default(false)->change();

            // Číselné skóre – nikdy NULL, 0 je výchozí hodnota.
            $table->integer('pocet')->default(0)->change();
            $table->integer('bodu_za_qso')->default(1)->change();
            $table->integer('nasobice')->default(0)->change();
            $table->integer('body')->default(0)->change();
            $table->integer('EDI_ID')->default(0)->change();
            $table->integer('poradi')->default(0)->change();

            // Textové sloupce – prázdný řetězec vyjadřuje „nevyplněno" lépe než NULL.
            $table->string('locator', 6)->default('')->change();
            $table->string('jmeno', 60)->default('')->change();
            $table->string('mail', 250)->default('')->change();
            $table->string('telefon', 20)->default('')->change();
            $table->string('poznamka', 250)->default('')->change();
            $table->string('soapbox', 250)->default('')->change();
            $table->string('ip', 64)->default('')->change();
            $table->string('session_id', 255)->default('')->change();
        });

        // 3. Kompozitní index (IDS, Time) na edilines.
        Schema::table('edilines', function (Blueprint $table): void {
            $table->dropIndex('edilines_time_idx');
            $table->index(['IDS', 'Time'], 'edilines_ids_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->dropUnique('vkvpa_prihlaseni_kod_unique');
        });

        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->boolean('qrp')->nullable()->default(false)->change();
            $table->boolean('lp')->nullable()->default(false)->change();
            $table->boolean('EDI')->nullable()->default(false)->change();
            $table->boolean('schvaleno')->nullable()->default(false)->change();
            $table->integer('pocet')->nullable()->default(0)->change();
            $table->integer('bodu_za_qso')->nullable()->default(1)->change();
            $table->integer('nasobice')->nullable()->default(0)->change();
            $table->integer('body')->nullable()->default(0)->change();
            $table->integer('EDI_ID')->nullable()->default(0)->change();
            $table->integer('poradi')->nullable()->default(0)->change();
            $table->string('locator', 6)->nullable()->default('')->change();
            $table->string('jmeno', 60)->nullable()->default('')->change();
            $table->string('mail', 250)->nullable()->default('')->change();
            $table->string('telefon', 20)->nullable()->default('')->change();
            $table->string('poznamka', 250)->nullable()->default('')->change();
            $table->string('soapbox', 250)->nullable()->default('')->change();
            $table->string('ip', 64)->nullable()->default('')->change();
            $table->string('session_id', 255)->nullable()->default('')->change();
        });

        Schema::table('edilines', function (Blueprint $table): void {
            $table->dropIndex('edilines_ids_time_idx');
            $table->index('Time', 'edilines_time_idx');
        });
    }
};

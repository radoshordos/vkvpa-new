<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `edi_category` – jediný číselník kategorií aplikace (normalizovaný:
 * pásmo × sekce × varianta). Nahradil původní plochou `vkvpa_kategorie`.
 *
 * Kategorie závodu je zde rozložená do tří explicitních os místo toho, aby
 * byly „zašifrované" v textovém názvu/zkratce:
 *   band    – pásmo včetně jednotky ('144 MHz', '432 MHz', '1.3 GHz', … '122 GHz');
 *             shodné s výstupem CategoryResolver::band(), takže párování je triviální.
 *   section – sekce: 'SO' (single op) / 'MO' (multi op).
 *   variant – 'domestic' (tuzemská OK/OL) / 'dx' (zahraniční stanice).
 *
 * `dxid` váže DX řádek na jeho tuzemský protějšek (stejné band+section,
 * variant='domestic'); u tuzemských řádků je NULL. Self-FK na `edi_category.id`.
 * Přirozený klíč (band, section, variant) je unikátní – jedna kombinace =
 * jedna kategorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_category', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('band', 10);             // pásmo s jednotkou ('144 MHz', '1.3 GHz', …)
            $table->enum('section', ['SO', 'MO']);  // single op / multi op
            $table->enum('variant', ['domestic', 'dx']);
            $table->string('name', 50);             // čitelný název pro UI
            $table->integer('dxid')->nullable();    // tuzemský protějšek DX řádku; NULL = je tuzemská

            $table->unique(['band', 'section', 'variant'], 'edi_category_band_section_variant_unique');
            $table->index('dxid', 'edi_category_dxid_index');
        });

        // Self-FK přidáváme až po CREATE (SQLite ALTER ADD FOREIGN KEY neumí –
        // testovací DB jede bez ní, integritu tam hlídá aplikace + seed).
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('edi_category', function (Blueprint $table): void {
                $table->foreign('dxid', 'edi_category_dxid_foreign')
                    ->references('id')->on('edi_category')
                    ->nullOnDelete();
            });

            // edi_category je jediný číselník kategorií (vkvpa_kategorie zrušena).
            // FK vkvpa_data.id_kategorie sem patří až teď – vkvpa_data (000006)
            // se vytváří dřív, ale edi_category až tady.
            Schema::table('vkvpa_data', function (Blueprint $table): void {
                $table->foreign('id_kategorie', 'vkvpa_data_id_kategorie_fk')
                    ->references('id')->on('edi_category')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('vkvpa_data', function (Blueprint $table): void {
                $table->dropForeign('vkvpa_data_id_kategorie_fk');
            });
        }

        Schema::dropIfExists('edi_category');
    }
};

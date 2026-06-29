<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `edi_categories` – jediný číselník kategorií aplikace (normalizovaný:
 * pásmo × sekce × varianta). Nahradil původní plochou `vkvpa_kategorie`.
 *
 * Kategorie závodu je zde rozložená do tří explicitních os místo toho, aby
 * byly „zašifrované" v textovém názvu/zkratce:
 *   band_id – normalizovaný FK do číselníku pásem `edi_bands` (jediný zdroj
 *             pravdy o pásmu; název se čte přes relaci). Nullable, protože
 *             syntetické (testovací) řádky mívají neznámé pásmo (NULL).
 *   section – sekce: 'SO' (single op) / 'MO' (multi op).
 *   variant – 'domestic' (tuzemská OK/OL) / 'dx' (zahraniční stanice).
 *
 * `dxid` váže DX řádek na jeho tuzemský protějšek (stejné band+section,
 * variant='domestic'); u tuzemských řádků je NULL. Self-FK na `edi_categories.id`.
 * Přirozený klíč (band_id, section, variant) je unikátní – jedna kombinace =
 * jedna kategorie. NULL band_id se v unikátu chová jako různý (SQL), takže
 * syntetické řádky bez pásma spolu nekolidují.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_categories', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('band_id')->nullable();    // FK → edi_bands.id (zdroj pravdy o pásmu); NULL = neznámé
            $table->enum('section', ['SO', 'MO']);     // single op / multi op
            $table->enum('variant', ['domestic', 'dx']);
            $table->string('name', 50);                // čitelný název pro UI
            $table->integer('dxid')->nullable();       // tuzemský protějšek DX řádku; NULL = je tuzemská

            $table->unique(['band_id', 'section', 'variant'], 'edi_categories_band_section_variant_unique');
            $table->index('dxid', 'edi_categories_dxid_index');
        });

        // Self-FK přidáváme až po CREATE (SQLite ALTER ADD FOREIGN KEY neumí –
        // testovací DB jede bez ní, integritu tam hlídá aplikace + seed).
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('edi_categories', function (Blueprint $table): void {
                $table->foreign('dxid', 'edi_categories_dxid_foreign')
                    ->references('id')->on('edi_categories')
                    ->nullOnDelete();

                // band_id míří do číselníku pásem (edi_bands); mazání pásma se
                // nesmí kaskádovat – restrictOnDelete (NULL je povolen u neznámého).
                $table->foreign('band_id', 'edi_categories_band_id_foreign')
                    ->references('id')->on('edi_bands')
                    ->restrictOnDelete();
            });

            // edi_categories je jediný číselník kategorií (vkvpa_kategorie zrušena).
            // FK edi_entries.category_id sem patří až teď – edi_entries (000006)
            // se vytváří dřív, ale edi_categories až tady.
            Schema::table('edi_entries', function (Blueprint $table): void {
                $table->foreign('category_id', 'edi_entries_category_id_fk')
                    ->references('id')->on('edi_categories')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('edi_entries', function (Blueprint $table): void {
                $table->dropForeign('edi_entries_category_id_fk');
            });
        }

        Schema::dropIfExists('edi_categories');
    }
};

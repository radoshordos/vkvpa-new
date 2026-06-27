<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Propojení hlavičky deníku na normalizovanou kategorii (`edi_category`).
 *
 * Zatím čistě strukturální krok: sloupec je nullable a u všech existujících
 * řádků zůstává NULL. Naplnění (párování deníku na band × section × variant)
 * přijde samostatně.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edihead', function (Blueprint $table): void {
            $table->integer('edi_category_id')->nullable()->after('id_kola');
            $table->index('edi_category_id', 'edihead_edi_category_id_idx');
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('edihead', function (Blueprint $table): void {
            $table->foreign('edi_category_id', 'edihead_edi_category_id_fk')
                ->references('id')->on('edi_category')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edihead', function (Blueprint $table): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('edihead_edi_category_id_fk');
            }

            $table->dropIndex('edihead_edi_category_id_idx');
            $table->dropColumn('edi_category_id');
        });
    }
};

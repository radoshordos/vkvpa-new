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
        // Dvojice EDI (bool) + EDI_ID (0 = bez deníku) kódovala jeden fakt dvěma
        // sloupci a magickou nulou bez referenční integrity. Nahrazuje ji
        // nullable edihead_id: NULL = hlášení bez deníku, jinak FK na edihead.
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            // Typ musí přesně sedět na edihead.id (signed INT), jinak MySQL FK odmítne.
            $table->integer('edihead_id')->nullable();
            $table->index('edihead_id', 'vkvpa_data_edihead_id_idx');
        });

        // Backfill: osiřelé EDI_ID (bez existující hlavičky) zůstanou NULL,
        // aby šel cizí klíč založit.
        DB::statement(
            'UPDATE vkvpa_data SET edihead_id = EDI_ID
             WHERE EDI_ID > 0 AND EXISTS (SELECT 1 FROM edihead WHERE edihead.id = vkvpa_data.EDI_ID)',
        );

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('vkvpa_data', function (Blueprint $table): void {
                // Smazání deníku odpojí jen vazbu – výsledkový řádek (body jsou
                // denormalizované) zůstává, stejně jako při mazání v adminu.
                $table->foreign('edihead_id', 'vkvpa_data_edihead_id_fk')
                    ->references('id')->on('edihead')
                    ->nullOnDelete();
            });
        }

        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropColumn(['EDI', 'EDI_ID']);
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->boolean('EDI')->default(false);
            $table->integer('EDI_ID')->default(0);
        });

        DB::statement('UPDATE vkvpa_data SET EDI_ID = edihead_id, EDI = 1 WHERE edihead_id IS NOT NULL');

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('vkvpa_data', function (Blueprint $table): void {
                $table->dropForeign('vkvpa_data_edihead_id_fk');
            });
        }

        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropIndex('vkvpa_data_edihead_id_idx');
            $table->dropColumn('edihead_id');
        });
    }
};

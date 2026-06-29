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
        Schema::create('edi_entries', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // Sloupce s explicitním DEFAULT jsou NOT NULL – aplikace do nich
            // NULL nikdy neukládá, prázdný řetězec/0/false vyjadřuje „nevyplněno".
            $table->integer('id', true);
            $table->integer('round_id');
            $table->integer('category_id')->nullable();
            $table->boolean('qrp')->default(false);
            $table->boolean('lp')->default(false);
            $table->string('callsign', 10);
            $table->string('locator', 6)->default('');
            $table->integer('qso_count')->default(0);
            $table->integer('qso_points')->default(1);
            $table->integer('multiplier')->default(0);
            $table->integer('points')->default(0);
            $table->string('name', 60)->default('');
            $table->string('email', 250)->default('');
            $table->string('phone', 20)->default('');
            $table->string('note', 250)->default('');
            $table->string('soapbox', 250)->default('');
            $table->string('ip', 64)->default('');
            // NULL = hlášení bez deníku, jinak FK na edi_heads. Smazání deníku
            // odpojí jen vazbu – výsledkový řádek (body jsou denormalizované)
            // zůstává, stejně jako při mazání v adminu.
            $table->integer('edi_head_id')->nullable();
            $table->integer('rank')->default(0);
            $table->boolean('approved')->default(false);
            $table->boolean('sent')->default(false);
            $table->string('session_id', 255)->default('');
            // DATETIME (ne TIMESTAMP) – nezávislé na session time_zone serveru.
            $table->dateTime('submitted_at')->nullable()->useCurrent();

            // Historická data mají jednu značku ve více kategoriích (pásmech)
            // v rámci jednoho kola – unikátnost platí až na úrovni kategorie.
            $table->unique(['round_id', 'callsign', 'category_id'], 'edi_entries_round_callsign_category_unique');
            $table->index(['round_id', 'callsign', 'approved'], 'edi_entries_round_callsign_approved_idx');
            $table->index('category_id', 'edi_entries_category_id_idx');
            $table->index(['round_id', 'approved'], 'edi_entries_round_approved_idx');
            $table->index('edi_head_id', 'edi_entries_edi_head_id_idx');
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('edi_entries', function (Blueprint $table): void {
            // Kola se nikdy nemažou → RESTRICT (CASCADE by při omylu smazal výsledky).
            $table->foreign('round_id', 'edi_entries_round_id_fk')
                ->references('id')->on('edi_rounds')
                ->restrictOnDelete();
            // FK category_id → edi_categories se přidává až v migraci, která
            // edi_categories vytváří (2026_06_27_000001) – ta tabulka tu ještě neexistuje.
            $table->foreign('edi_head_id', 'edi_entries_edi_head_id_fk')
                ->references('id')->on('edi_heads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_entries');
    }
};

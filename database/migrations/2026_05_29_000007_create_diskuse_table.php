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
        Schema::create('diskuse', function (Blueprint $table): void {
            $table->id();
            // SIGNED INT kvůli shodě typu s edi_rounds.id (MySQL FK vyžaduje
            // shodné znaménko).
            $table->integer('round_id');
            $table->string('znacka', 20);
            $table->string('jmeno', 100)->nullable();
            $table->text('text');
            // Fotografie už nejsou na disku jako jediná cesta v tomto sloupci –
            // ukládají se binárně do podřízené tabulky `diskuse_foto` (1:N),
            // takže jich může být víc na jeden příspěvek.
            $table->string('ip', 45)->nullable();
            // DATETIME (ne TIMESTAMP) – nezávislé na session time_zone serveru.
            $table->dateTime('created_at')->useCurrent();

            $table->index('round_id');
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Kola se nikdy nemažou → RESTRICT.
        Schema::table('diskuse', function (Blueprint $table): void {
            $table->foreign('round_id', 'diskuse_round_id_fk')
                ->references('id')->on('edi_rounds')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diskuse');
    }
};

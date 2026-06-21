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
            // SIGNED INT kvůli shodě typu s vkvpa_kola.id (MySQL FK vyžaduje
            // shodné znaménko).
            $table->integer('kolo_id');
            $table->string('znacka', 20);
            $table->string('jmeno', 100)->nullable();
            $table->text('text');
            // Fotografie už nejsou na disku jako jediná cesta v tomto sloupci –
            // ukládají se binárně do podřízené tabulky `diskuse_foto` (1:N),
            // takže jich může být víc na jeden příspěvek.
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('kolo_id');
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Kola se nikdy nemažou → RESTRICT.
        Schema::table('diskuse', function (Blueprint $table): void {
            $table->foreign('kolo_id', 'diskuse_kolo_id_fk')
                ->references('id')->on('vkvpa_kola')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diskuse');
    }
};

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
        Schema::create('diskuse_foto', function (Blueprint $table): void {
            $table->id();
            // SIGNED kvůli shodě typu s diskuse.id (MySQL FK vyžaduje shodné
            // znaménko); diskuse.id je BIGINT (id()), proto i zde unsignedBigInteger
            // by neseděl – použijeme stejný typ jako u rodiče.
            $table->unsignedBigInteger('prispevek_id');
            $table->string('mime', 64);
            // Hlavní (zmenšený) obrázek a náhled jako binární data přímo v DB.
            // binary() dá na MySQL jen 64 KB BLOB; níže to v MySQL větvi povýšíme
            // na MEDIUMBLOB (16 MB). SQLite má BLOB bez praktického limitu, takže
            // tam stačí takto.
            $table->binary('data');
            $table->binary('nahled');
            $table->unsignedSmallInteger('sirka')->default(0);
            $table->unsignedSmallInteger('vyska')->default(0);
            $table->unsignedInteger('velikost')->default(0);
            $table->unsignedSmallInteger('poradi')->default(0);
            // DATETIME (ne TIMESTAMP) – nezávislé na session time_zone serveru.
            $table->dateTime('created_at')->useCurrent();

            $table->index(['prispevek_id', 'poradi']);
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Povýšení BLOB → MEDIUMBLOB (16 MB), aby se vešly zmenšené fotky.
        DB::statement('ALTER TABLE diskuse_foto MODIFY data MEDIUMBLOB NOT NULL');
        DB::statement('ALTER TABLE diskuse_foto MODIFY nahled MEDIUMBLOB NOT NULL');

        // Smazání příspěvku odstraní i jeho fotky.
        Schema::table('diskuse_foto', function (Blueprint $table): void {
            $table->foreign('prispevek_id', 'diskuse_foto_prispevek_id_fk')
                ->references('id')->on('diskuse')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diskuse_foto');
    }
};

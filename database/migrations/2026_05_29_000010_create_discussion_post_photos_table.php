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
        Schema::create('discussion_post_photos', function (Blueprint $table): void {
            $table->id();
            // SIGNED kvůli shodě typu s discussion_posts.id (MySQL FK vyžaduje
            // shodné znaménko); discussion_posts.id je BIGINT (id()), proto
            // použijeme stejný typ i zde.
            $table->unsignedBigInteger('discussion_post_id');
            $table->string('mime_type', 64);
            // Hlavní (zmenšený) obrázek a náhled jako binární data přímo v DB.
            // binary() dá na MySQL jen 64 KB BLOB; níže to v MySQL větvi povýšíme
            // na MEDIUMBLOB (16 MB). SQLite má BLOB bez praktického limitu, takže
            // tam stačí takto.
            $table->binary('data');
            $table->binary('thumbnail');
            $table->unsignedSmallInteger('width')->default(0);
            $table->unsignedSmallInteger('height')->default(0);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            // DATETIME (ne TIMESTAMP) – nezávislé na session time_zone serveru.
            $table->dateTime('created_at')->useCurrent();

            $table->index(['discussion_post_id', 'position']);
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Povýšení BLOB → MEDIUMBLOB (16 MB), aby se vešly zmenšené fotky.
        DB::statement('ALTER TABLE discussion_post_photos MODIFY data MEDIUMBLOB NOT NULL');
        DB::statement('ALTER TABLE discussion_post_photos MODIFY thumbnail MEDIUMBLOB NOT NULL');

        // Smazání příspěvku odstraní i jeho fotky.
        Schema::table('discussion_post_photos', function (Blueprint $table): void {
            $table->foreign('discussion_post_id', 'discussion_post_photos_post_id_fk')
                ->references('id')->on('discussion_posts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_post_photos');
    }
};

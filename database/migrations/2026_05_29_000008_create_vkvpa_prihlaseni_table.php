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
        Schema::create('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            // DATETIME (ne TIMESTAMP) – nezávislé na session time_zone serveru.
            $table->dateTime('time')->nullable()->useCurrent();
            // Přihlašovací kódy musí být unikátní – kolize tokenu by jinak
            // umožnila neoprávněné přihlášení. Šířka 64 pojme celý SHA-256 hash
            // (kratší sloupec by hash ořezal a token by se nikdy nenašel).
            $table->string('kod', 64);
            // Magic-link token přihlašuje konkrétního uživatele (auditní stopa
            // i při více administrátorech).
            $table->unsignedBigInteger('user_id')->nullable();

            $table->unique('kod', 'vkvpa_prihlaseni_kod_unique');
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->foreign('user_id', 'vkvpa_prihlaseni_user_id_fk')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_prihlaseni');
    }
};

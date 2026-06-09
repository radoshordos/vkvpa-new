<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->timestamp('time')->nullable()->useCurrent();
            // Přihlašovací kódy musí být unikátní – kolize tokenu by jinak
            // umožnila neoprávněné přihlášení. Šířka 64 pojme celý SHA-256 hash
            // (kratší sloupec by hash ořezal a token by se nikdy nenašel).
            $table->string('kod', 64);
            // Magic-link token přihlašuje konkrétního uživatele (auditní stopa
            // i při více administrátorech). FK na users.id doplňuje migrace
            // add_foreign_keys_to_vkvpa_tables.
            $table->unsignedBigInteger('user_id')->nullable();

            $table->unique('kod', 'vkvpa_prihlaseni_kod_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_prihlaseni');
    }
};

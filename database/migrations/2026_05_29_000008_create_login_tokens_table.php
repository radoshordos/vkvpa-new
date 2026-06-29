<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_tokens', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            // Přihlašovací tokeny musí být unikátní – kolize tokenu by jinak
            // umožnila neoprávněné přihlášení. Šířka 64 pojme celý SHA-256 hash
            // (kratší sloupec by hash ořezal a token by se nikdy nenašel).
            $table->string('token', 64)->unique();
            // Magic-link token přihlašuje konkrétního uživatele (auditní stopa
            // i při více administrátorech). FK je inline – funguje i v SQLite
            // (testovací DB) v rámci CREATE TABLE.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_tokens');
    }
};

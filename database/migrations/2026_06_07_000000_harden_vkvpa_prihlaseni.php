<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zpevnění tabulky přihlašovacích tokenů:
 *  - `kod` rozšířen na 64 znaků, aby pojal celý SHA-256 hash (dříve varchar(32)
 *    ořezával hash → token by se v MySQL nikdy nenašel a přihlášení selhalo);
 *  - přidán `user_id`, takže magic-link token přihlašuje konkrétního uživatele
 *    a zachovává auditní stopu i při více administrátorech.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->string('kod', 64)->change();
            $table->unsignedBigInteger('user_id')->nullable()->after('kod');
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->dropColumn('user_id');
            $table->string('kod', 32)->change();
        });
    }
};

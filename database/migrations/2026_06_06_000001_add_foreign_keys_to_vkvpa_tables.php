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
        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // diskuse.kolo_id bylo UNSIGNED INT, ale vkvpa_kola.id je SIGNED INT –
        // MySQL vyžaduje pro FK shodný typ včetně znaménka.
        Schema::table('diskuse', function (Blueprint $table): void {
            $table->integer('kolo_id')->change();
        });

        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->foreign('id_kola', 'vkvpa_data_id_kola_fk')
                ->references('id')->on('vkvpa_kola')
                ->cascadeOnDelete();

            $table->foreign('id_kategorie', 'vkvpa_data_id_kategorie_fk')
                ->references('id')->on('vkvpa_kategorie')
                ->restrictOnDelete();
        });

        Schema::table('diskuse', function (Blueprint $table): void {
            $table->foreign('kolo_id', 'diskuse_kolo_id_fk')
                ->references('id')->on('vkvpa_kola')
                ->cascadeOnDelete();
        });

        Schema::table('edihead', function (Blueprint $table): void {
            $table->foreign('id_kola', 'edihead_id_kola_fk')
                ->references('id')->on('vkvpa_kola')
                ->cascadeOnDelete();
        });

        Schema::table('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->foreign('user_id', 'vkvpa_prihlaseni_user_id_fk')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropForeign('vkvpa_data_id_kola_fk');
            $table->dropForeign('vkvpa_data_id_kategorie_fk');
        });

        Schema::table('diskuse', function (Blueprint $table): void {
            $table->dropForeign('diskuse_kolo_id_fk');
            $table->unsignedInteger('kolo_id')->change();
        });

        Schema::table('edihead', function (Blueprint $table): void {
            $table->dropForeign('edihead_id_kola_fk');
        });

        Schema::table('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->dropForeign('vkvpa_prihlaseni_user_id_fk');
        });
    }
};

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
            // umožnila neoprávněné přihlášení.
            $table->string('kod', 32);

            $table->unique('kod', 'vkvpa_prihlaseni_kod_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_prihlaseni');
    }
};

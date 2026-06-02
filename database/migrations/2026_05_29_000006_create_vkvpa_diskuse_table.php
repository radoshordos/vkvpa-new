<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_diskuse', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('id_kola');
            $table->dateTime('cas');
            $table->string('znacka', 20);
            $table->string('jmeno', 50)->nullable();
            $table->text('text');
            $table->string('foto', 100)->nullable();
            $table->string('ip', 45)->nullable();

            $table->index('id_kola', 'vkvpa_diskuse_id_kola_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_diskuse');
    }
};

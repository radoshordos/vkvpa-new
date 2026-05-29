<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_kategorie', function (Blueprint $table): void {
            $table->charset = 'utf8mb3';
            $table->collation = 'utf8mb3_general_ci';

            $table->integer('id', true);
            $table->string('nazev', 50);
            $table->string('popis', 250);
            $table->string('zkratka', 20);
            $table->integer('dxid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_kategorie');
    }
};

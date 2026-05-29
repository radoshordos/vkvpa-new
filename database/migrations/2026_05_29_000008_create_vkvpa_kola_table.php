<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_kola', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->date('datum_konani');
            $table->dateTime('datum_uzaverky');
            $table->string('nazev', 250);
            $table->dateTime('vyhodnoceno', 1)->nullable();
            $table->string('poznamka', 250);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_kola');
    }
};

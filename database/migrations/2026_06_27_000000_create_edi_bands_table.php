<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `edi_bands` – číselník pásem (band). Vytažený z denormalizovaného textového
 * sloupce `edi_categories.band`, na který nyní `edi_categories.band_id` ukazuje FK.
 *
 *   token – kanonický token pásma bez jednotky ('144', '432', '1.3', … '122');
 *           shodný s prvním tokenem `name` a s výstupem CategoryResolver::band().
 *   name  – čitelný štítek s jednotkou ('144 MHz', '1.3 GHz', … '122 GHz').
 *
 * Pořadí pásem (144 → 122 GHz) odpovídá rostoucímu `id`, takže `orderBy('id')`
 * dává přirozené řazení i bez zvláštního sloupce. Vytváří se PŘED `edi_categories`
 * (000001), aby na něj šel navázat cizí klíč.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_bands', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('token', 8);   // kanonický token bez jednotky ('144', '1.3', …)
            $table->string('name', 10);   // čitelný štítek s jednotkou ('144 MHz', '1.3 GHz', …)

            $table->unique('token', 'edi_bands_token_unique');
            $table->unique('name', 'edi_bands_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_bands');
    }
};

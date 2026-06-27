<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `edi_category` – normalizovaná obdoba `vkvpa_kategorie`.
 *
 * Kategorie závodu je zde rozložená do tří explicitních os místo toho, aby
 * byly „zašifrované" v textovém názvu/zkratce:
 *   band    – kanonický token pásma ('144', '432', '1.3', … '122'); shodný
 *             s výstupem CategoryResolver::band(), takže párování je triviální.
 *   section – sekce: 'SO' (single op) / 'MO' (multi op).
 *   variant – 'domestic' (tuzemská OK/OL) / 'dx' (zahraniční stanice).
 *
 * Dvojice DX → tuzemská kategorie se NEukládá (žádné `dxid`); odvodí se dotazem
 * na řádek se stejným band+section a variant='domestic'. Přirozený klíč
 * (band, section, variant) je unikátní – jedna kombinace = jedna kategorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_category', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('band', 8);              // kanonický token pásma ('144', '1.3', …)
            $table->enum('section', ['SO', 'MO']);  // single op / multi op
            $table->enum('variant', ['domestic', 'dx']);
            $table->string('name', 50);             // čitelný název pro UI

            $table->unique(['band', 'section', 'variant'], 'edi_category_band_section_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_category');
    }
};

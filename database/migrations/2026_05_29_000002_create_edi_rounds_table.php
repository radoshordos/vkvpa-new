<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_rounds', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            // Start závodu je přímo v `starts_at` (datetime); stav kola se
            // odvozuje čistě z času. Kolo se koná jednou za měsíc → na jeden
            // den nejvýš jedno kolo, proto unikát (párování deníků dle TDate).
            $table->dateTime('starts_at');
            $table->dateTime('closes_at');
            $table->string('name', 250);
            $table->dateTime('evaluated_at')->nullable();
            $table->string('note', 250);

            $table->unique('starts_at', 'edi_rounds_starts_at_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_rounds');
    }
};

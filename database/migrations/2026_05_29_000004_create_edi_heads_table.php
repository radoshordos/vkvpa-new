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
        Schema::create('edi_heads', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('round_id')->nullable();
            $table->string('t_date', 17);
            $table->string('p_call', 30);
            $table->string('p_wwlo', 6);
            $table->string('p_sect', 20)->default('');
            $table->string('p_band', 20)->default('');
            $table->string('r_name', 50);
            $table->string('r_emai', 100)->nullable();
            $table->string('r_phon', 50)->default('');
            $table->unsignedSmallInteger('s_powe');
            $table->string('s_tx_eq', 100)->nullable();
            $table->string('s_ante', 100)->nullable();
            $table->mediumText('src')->nullable();
            $table->text('remarks')->nullable();
            // DATETIME (ne TIMESTAMP) – časy jsou nezávislé na session time_zone
            // serveru, takže se snapshoty/dumpy (v UTC) reprodukují 1:1.
            $table->dateTime('stamp')->useCurrent();
            $table->dateTime('d_cas')->nullable()->useCurrent();
            $table->longText('s_rcr')->nullable();

            $table->index('round_id', 'edi_heads_round_id_idx');
            $table->index('p_call', 'edihead_pcall_idx');
        });

        // SQLite (testovací DB) neumí přidat cizí klíč přes ALTER TABLE; FK se
        // tam vynucují jinak a referenční integritu řeší aplikace + testy.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Kola se nikdy nemažou → RESTRICT brání tichému smazání navázaných deníků.
        Schema::table('edi_heads', function (Blueprint $table): void {
            $table->foreign('round_id', 'edi_heads_round_id_fk')
                ->references('id')->on('edi_rounds')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_heads');
    }
};

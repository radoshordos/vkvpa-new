<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edihead', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('id_kola')->nullable()->default(0);
            $table->string('t_date', 17);
            $table->string('p_call', 30);
            $table->string('p_wwlo', 6);
            $table->string('p_sect', 20)->default('');
            $table->string('p_band', 20)->default('');
            $table->string('r_name', 50);
            $table->string('r_emai', 100)->nullable();
            $table->string('r_phon', 50)->default('');
            $table->string('r_hbbs', 50);
            $table->unsignedSmallInteger('s_powe');
            $table->string('s_tx_eq', 100)->nullable();
            $table->string('s_ante', 100)->nullable();
            $table->mediumText('src')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('stamp')->useCurrent();
            $table->timestamp('d_cas')->nullable()->useCurrent();
            $table->longText('s_rcr')->nullable();

            $table->index('id_kola', 'edihead_id_kola_idx');
            $table->index('p_call', 'edihead_pcall_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edihead');
    }
};

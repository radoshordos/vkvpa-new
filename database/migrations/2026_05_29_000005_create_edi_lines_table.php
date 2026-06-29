<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_lines', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->integer('edi_head_id');
            // Datum+čas QSO v UTC – kanonický zdroj časování spojení (původní
            // textové sloupce date 'YYMMDD' + time 'HHMM' byly nahrazeny tímto).
            $table->dateTime('qso_at')->nullable();
            $table->string('call_sign', 30)->nullable();
            $table->integer('mode_code')->nullable();
            $table->string('sent_rst', 3)->nullable();
            $table->integer('sent_qso_number')->nullable();
            $table->string('received_rst', 3)->nullable();
            $table->integer('received_qso_number')->nullable();
            $table->string('received_exchange', 10)->nullable();
            $table->string('received_wwl', 6)->nullable();
            $table->integer('qso_points')->nullable();
            $table->string('new_exchange_n', 10)->nullable();
            $table->string('new_wwl_n', 1)->nullable();
            $table->string('new_dxcc_n', 1)->nullable();
            $table->string('duplicate_qso_d', 1)->nullable();
            $table->smallInteger('sqr')->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->decimal('lat', 8, 6)->nullable();

            $table->index('edi_head_id', 'edi_lines_edi_head_id_index');
            $table->index('received_wwl', 'edi_lines_received_wwl_index');
            $table->index(['edi_head_id', 'qso_at'], 'edi_lines_edi_head_id_qso_at_idx');
            $table->foreign('edi_head_id', 'edi_lines_edi_head_id_foreign')->references('id')->on('edi_heads');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_lines');
    }
};

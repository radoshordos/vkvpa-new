<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edilines', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('edihead_id');
            $table->string('date', 6)->nullable();
            $table->string('time', 4)->nullable();
            // Sloučené datum+čas QSO v UTC (date 'YYMMDD' + time 'HHMM').
            // Plní se u nových importů; sloupce date/time zatím zůstávají.
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

            $table->index('edihead_id', 'edihead_id');
            $table->index('received_wwl', 'received_wwl');
            $table->index(['edihead_id', 'time'], 'edilines_edihead_id_time_idx');
            $table->foreign('edihead_id', 'edilines_ibfk_1')->references('id')->on('edihead');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edilines');
    }
};

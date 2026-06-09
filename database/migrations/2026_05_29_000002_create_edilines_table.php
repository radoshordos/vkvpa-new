<?php

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

            $table->integer('ID', true);
            $table->integer('IDS');
            $table->string('Date', 6)->nullable();
            $table->string('Time', 4)->nullable();
            $table->string('CallSign', 30)->nullable();
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

            $table->index('IDS', 'IDS');
            $table->index('received_wwl', 'received_wwl');
            // Dotazy QSO vždy filtrují podle deníku (IDS) i závodního okna (Time).
            $table->index(['IDS', 'Time'], 'edilines_ids_time_idx');
            $table->foreign('IDS', 'edilines_ibfk_1')->references('ID')->on('edihead');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edilines');
    }
};

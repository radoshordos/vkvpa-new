<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edilines', function (Blueprint $table): void {
            $table->integer('ID', true);
            $table->integer('IDS');
            $table->string('Date', 6)->nullable();
            $table->string('Time', 4)->nullable();
            $table->string('CallSign', 30)->nullable();
            $table->integer('Mode-code')->nullable();
            $table->string('Sent-RST', 3)->nullable();
            $table->integer('Sent QSO number')->nullable();
            $table->string('Received-RST', 3)->nullable();
            $table->integer('Received QSO number')->nullable();
            $table->string('Received exchange', 10)->nullable();
            $table->string('Received-WWL', 6)->nullable();
            $table->integer('QSO-Points')->nullable();
            $table->string('New-Exchange-(N)', 10)->nullable();
            $table->string('New-WWL-(N)', 1)->nullable();
            $table->string('New-DXCC-(N)', 1)->nullable();
            $table->string('Duplicate-QSO-(D)', 1)->nullable();
            $table->smallInteger('sqr')->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->decimal('lat', 8, 6)->nullable();

            $table->index('IDS', 'IDS');
            $table->index('Received-WWL', 'Received-WWL');
            $table->foreign('IDS', 'edilines_ibfk_1')->references('ID')->on('edihead');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edilines');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_data', function (Blueprint $table): void {
            $table->charset = 'utf8mb3';
            $table->collation = 'utf8mb3_general_ci';

            $table->integer('id', true);
            $table->integer('id_kola');
            $table->integer('id_kategorie');
            $table->boolean('qrp')->nullable()->default(false);
            $table->boolean('lp')->nullable()->default(false);
            $table->string('znacka', 10);
            $table->string('locator', 6)->nullable()->default('');
            $table->integer('pocet')->nullable()->default(0);
            $table->integer('bodu_za_qso')->nullable()->default(1);
            $table->integer('nasobice')->nullable()->default(0);
            $table->integer('body')->nullable()->default(0);
            $table->string('jmeno', 60)->nullable()->default('');
            $table->string('mail', 250)->nullable()->default('');
            $table->string('telefon', 20)->nullable()->default('');
            $table->string('poznamka', 250)->nullable()->default('');
            $table->string('soapbox', 250)->nullable()->default('');
            $table->string('ip', 64)->nullable()->default('');
            $table->boolean('EDI')->nullable()->default(false);
            $table->integer('EDI_ID')->nullable()->default(0);
            $table->integer('poradi')->nullable()->default(0);
            $table->boolean('schvaleno')->nullable()->default(false);
            $table->boolean('odeslano')->default(false);
            $table->string('session_id', 255)->nullable()->default('');
            $table->timestamp('timestamp')->nullable()->useCurrent();

            $table->index(['id_kola', 'znacka', 'schvaleno'], 'data1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_data');
    }
};

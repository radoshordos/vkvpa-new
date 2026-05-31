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

            $table->integer('ID', true);
            $table->integer('id_kola')->nullable()->default(0);
            $table->string('TDate', 17);
            $table->string('PCall', 30);
            $table->string('PWWLo', 6);
            $table->string('PSect', 20)->default('');
            $table->string('PBand', 20)->default('');
            $table->string('RName', 50);
            $table->string('REmai', 100)->nullable();
            $table->string('RPhon', 50)->default('');
            $table->string('RHBBS', 50);
            $table->unsignedSmallInteger('SPowe');
            $table->string('STXEq', 100)->nullable();
            $table->string('SAnte', 100)->nullable();
            $table->mediumText('src')->nullable();
            $table->text('Remarks')->nullable();
            $table->timestamp('stamp')->useCurrent();
            $table->timestamp('d_cas')->nullable()->useCurrent();
            $table->longText('SRCR')->nullable();

            $table->index('id_kola', 'edihead_id_kola_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edihead');
    }
};

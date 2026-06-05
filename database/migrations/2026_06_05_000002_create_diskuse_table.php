<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diskuse', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('kolo_id');
            $table->string('znacka', 20);
            $table->string('jmeno', 100)->nullable();
            $table->text('text');
            $table->string('foto', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('kolo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diskuse');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prefixes', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', true);
            $table->string('prefix', 3);
            $table->string('country', 255);

            $table->index('prefix', 'prefix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prefixes');
    }
};

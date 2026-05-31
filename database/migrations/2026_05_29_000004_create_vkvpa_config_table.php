<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_config', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->string('cfg_key', 50)->primary();
            $table->text('cfg_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_config');
    }
};

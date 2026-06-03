<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->index('id_kategorie', 'vkvpa_data_id_kategorie_idx');
        });

        Schema::table('edilines', function (Blueprint $table): void {
            $table->index('Time', 'edilines_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->dropIndex('vkvpa_data_id_kategorie_idx');
        });

        Schema::table('edilines', function (Blueprint $table): void {
            $table->dropIndex('edilines_time_idx');
        });
    }
};

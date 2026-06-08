<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->integer('id_kategorie')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vkvpa_data', function (Blueprint $table): void {
            $table->integer('id_kategorie')->nullable(false)->change();
        });
    }
};

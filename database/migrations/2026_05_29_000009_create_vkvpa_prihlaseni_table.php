<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vkvpa_prihlaseni', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->timestamp('time')->nullable()->useCurrent();
            $table->string('kod', 40);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vkvpa_prihlaseni');
    }
};

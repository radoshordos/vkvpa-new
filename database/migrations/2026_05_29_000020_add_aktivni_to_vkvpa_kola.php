<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vkvpa_kola', 'aktivni')) {
            Schema::table('vkvpa_kola', function (Blueprint $table): void {
                $table->boolean('aktivni')->default(false)->after('nazev');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vkvpa_kola', 'aktivni')) {
            Schema::table('vkvpa_kola', function (Blueprint $table): void {
                $table->dropColumn('aktivni');
            });
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Start závodu je nově přímo v `datum_konani` (datetime) – dosud byl
        // jen den a čas 08:00 UTC se dopočítával. Stav kola se odvozuje čistě
        // z času, příznak `aktivni` tím ztrácí smysl.
        Schema::table('vkvpa_kola', function (Blueprint $table): void {
            $table->dateTime('datum_konani')->change();
        });

        // Existující řádky: den závodu → start závodu 08:00:00 UTC.
        // Po PHP řádcích kvůli přenositelnosti mezi MySQL a SQLite.
        foreach (DB::table('vkvpa_kola')->get(['id', 'datum_konani']) as $row) {
            $datum = substr(is_scalar($row->datum_konani) ? (string) $row->datum_konani : '', 0, 10);

            if ($datum === '') {
                continue;
            }

            DB::table('vkvpa_kola')
                ->where('id', $row->id)
                ->update(['datum_konani' => $datum.' 08:00:00']);
        }

        if (Schema::hasColumn('vkvpa_kola', 'aktivni')) {
            Schema::table('vkvpa_kola', function (Blueprint $table): void {
                $table->dropColumn('aktivni');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vkvpa_kola', function (Blueprint $table): void {
            $table->boolean('aktivni')->default(false)->after('nazev');
            $table->date('datum_konani')->change();
        });
    }
};

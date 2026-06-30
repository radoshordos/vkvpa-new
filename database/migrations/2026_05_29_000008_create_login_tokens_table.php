<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_tokens', function (Blueprint $table): void {
            $table->id();
            // Token má tvar selector+verifier. „Selector" je náhodný veřejný
            // identifikátor pro O(1) vyhledání řádku (argon2 hash má náhodnou sůl,
            // takže přímé WHERE na hash nejde) – musí být unikátní, kolize by
            // umožnila záměnu tokenů.
            $table->string('selector', 16)->unique();
            // „Verifier" se ukládá jako argon2id hash (preferován před SHA-2):
            // únik DB tak nevydá použitelné tokeny. Šířka 255 pojme celý
            // argon2id hash; není unikátní (každý má vlastní sůl).
            $table->string('token');
            // Magic-link token přihlašuje konkrétního uživatele (auditní stopa
            // i při více administrátorech). FK je inline – funguje i v SQLite
            // (testovací DB) v rámci CREATE TABLE.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_tokens');
    }
};

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['name' => $name],
            [
                'password' => Hash::make($password),
                'is_admin' => true,
            ],
        );
        $this->command?->info("Admin účet '{$name}' připraven.");
    }
}

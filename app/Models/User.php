<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Uživatel administrace. Nahrazuje hardcoded login `Beda`/`oK1dOz` z head.php.
 *
 * Přihlašuje se uživatelským jménem (sloupec `name`), nikoli e-mailem,
 * kvůli zachování stávajícího chování (login „Beda").
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}

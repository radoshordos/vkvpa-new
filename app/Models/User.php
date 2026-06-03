<?php

declare(strict_types=1);

namespace App\Models;

use Override;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Uživatel administrace.
 *
 * Přihlašuje se uživatelským jménem (sloupec `name`), nikoli e-mailem.
 */
#[Fillable([
    'name',
    'email',
    'password',
    'is_admin',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    use Notifiable;

    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}

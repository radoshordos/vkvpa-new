<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;

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

    // Authenticatable trait accesses remember_token via __get; opt-out prevents
    // MissingAttributeException during logout when the column isn't selected.
    protected static $modelsShouldPreventAccessingMissingAttributes = false;

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

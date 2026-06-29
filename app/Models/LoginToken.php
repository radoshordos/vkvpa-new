<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Jednorázové přihlašovací tokeny (magic-link) navázané na uživatele.
 *
 * @property int $id
 * @property string $token
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['token', 'user_id'])]
class LoginToken extends Model
{
    #[Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }
}

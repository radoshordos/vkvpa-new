<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Dočasné přihlašovací kódy pro přihlášení přes ?kod=.
 *
 * @property int $id
 * @property string $kod
 * @property int|null $user_id
 * @property Carbon $time
 */
#[Fillable(['time', 'kod', 'user_id'])]
#[Table(name: 'vkvpa_prihlaseni', key: 'id')]
#[WithoutTimestamps]
class VkvpaPrihlaseni extends Model
{
    #[Override]
    protected function casts(): array
    {
        return [
            'time' => 'datetime',
            'user_id' => 'integer',
        ];
    }
}

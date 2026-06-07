<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Dočasné přihlašovací kódy pro přihlášení přes ?kod=.
 *
 * @property int $id
 * @property string $kod
 * @property \Illuminate\Support\Carbon $time
 */
#[Fillable(['time', 'kod'])]
#[Table(name: 'vkvpa_prihlaseni', key: 'id')]
#[WithoutTimestamps]
class VkvpaPrihlaseni extends Model
{
    #[Override]
    protected function casts(): array
    {
        return [
            'time' => 'datetime',
        ];
    }
}

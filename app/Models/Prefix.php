<?php

declare(strict_types=1);

namespace App\Models;

use Override;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Číselník prefixů (přiřazení prefix → země pro DXCC).
 */
#[Table(name: 'prefixes', key: 'id')]
#[WithoutTimestamps]
class Prefix extends Model
{
    #[Override]
    protected $guarded = [];
}

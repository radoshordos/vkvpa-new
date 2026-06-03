<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Číselník prefixů (přiřazení prefix → země pro DXCC).
 */
#[Fillable(['prefix', 'country'])]
#[Table(name: 'prefixes', key: 'id')]
#[WithoutTimestamps]
class Prefix extends Model
{
}

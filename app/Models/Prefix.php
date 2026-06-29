<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Číselník prefixů (přiřazení prefix → země pro DXCC).
 *
 * @property int $id
 * @property string $prefix
 * @property string $country
 */
#[Fillable(['prefix', 'country'])]
#[Table(name: 'edi_prefixes', key: 'id')]
#[WithoutTimestamps]
class Prefix extends Model {}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Číselník prefixů (přiřazení prefix → země pro DXCC).
 */
class Prefix extends Model
{
    protected $table = 'prefixes';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}

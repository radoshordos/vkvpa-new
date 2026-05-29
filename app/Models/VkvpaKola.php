<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolo závodu (contest period).
 */
class VkvpaKola extends Model
{
    protected $table = 'vkvpa_kola';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'datum_konani' => 'date',
        'datum_uzaverky' => 'datetime',
        'vyhodnoceno' => 'datetime',
    ];

    public function hlaseni(): HasMany
    {
        return $this->hasMany(VkvpaData::class, 'id_kola', 'id');
    }

    public function diskuse(): HasMany
    {
        return $this->hasMany(VkvpaDiskuse::class, 'id_kola', 'id');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\VkvpaKola;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VkvpaKola */
final class KoloResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nazev' => $this->nazev,
            'datum_konani' => $this->datum_konani->format('Y-m-d'),
            'datum_uzaverky' => $this->datum_uzaverky?->toIso8601String(),
            'vyhodnoceno' => $this->vyhodnoceno?->toIso8601String(),
            'aktivni' => $this->aktivni,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\EdiEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EdiEntry */
final class VysledkaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'poradi' => $this->rank,
            'znacka' => $this->callsign,
            'jmeno' => $this->name,
            'locator' => $this->locator,
            'kategorie_id' => $this->category_id,
            'kategorie' => $this->category?->name,
            'body' => $this->points,
            'pocet' => $this->qso_count,
            'multiplier' => $this->multiplier,
            'qso_points' => $this->qso_points,
            'qrp' => $this->qrp,
            'edi' => $this->edi_head_id !== null,
        ];
    }
}

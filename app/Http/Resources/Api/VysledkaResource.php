<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\VkvpaData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VkvpaData */
final class VysledkaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'poradi' => $this->poradi,
            'znacka' => $this->znacka,
            'jmeno' => $this->jmeno,
            'locator' => $this->locator,
            'kategorie_id' => $this->id_kategorie,
            'kategorie' => $this->kategorie?->nazev,
            'body' => $this->body,
            'pocet' => $this->pocet,
            'nasobice' => $this->nasobice,
            'bodu_za_qso' => $this->bodu_za_qso,
            'qrp' => $this->qrp,
            'edi' => $this->EDI,
        ];
    }
}

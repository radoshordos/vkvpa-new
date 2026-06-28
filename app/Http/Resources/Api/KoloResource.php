<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\EdiRound;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EdiRound */
final class KoloResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nazev' => $this->name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'vyhodnoceno' => $this->evaluated_at?->toIso8601String(),
            'stav' => $this->state()->value,
        ];
    }
}

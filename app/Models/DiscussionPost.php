<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * Diskusní příspěvek navázaný na kolo závodu.
 *
 * @property int $id
 * @property int $round_id
 * @property string $callsign
 * @property string|null $name
 * @property string $body
 * @property string|null $ip_hash HMAC-SHA256 IP odesílatele (ne syrová PII)
 * @property Carbon $created_at
 * @property-read EdiRound $round
 * @property-read Collection<int, DiscussionPostPhoto> $photos
 */
#[Fillable(['round_id', 'callsign', 'name', 'body', 'ip_hash'])]
#[Table(name: 'discussion_posts', key: 'id')]
class DiscussionPost extends Model
{
    public const UPDATED_AT = null;

    /**
     * Zahashuje IP odesílatele (HMAC-SHA256 s app key) pro účely moderace.
     * Neukládáme syrovou IP – hash zachová korelaci stejného odesílatele, ale
     * není to čitelná osobní údaj.
     */
    public static function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $key = config('app.key');

        return hash_hmac('sha256', $ip, is_string($key) ? $key : '');
    }

    /** @return BelongsTo<EdiRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(EdiRound::class, 'round_id');
    }

    /** @return HasMany<DiscussionPostPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(DiscussionPostPhoto::class, 'discussion_post_id')->orderBy('position');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'round_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}

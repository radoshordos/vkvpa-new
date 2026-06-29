<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * Jedna fotografie diskusního příspěvku, uložená binárně v DB.
 *
 * @property int $id
 * @property int $discussion_post_id
 * @property string $mime_type MIME typ výstupního obrázku (např. image/jpeg)
 * @property string $data Binární data zmenšeného obrázku
 * @property string $thumbnail Binární data náhledu (thumbnail)
 * @property int $width
 * @property int $height
 * @property int $size_bytes Velikost hlavního obrázku v bajtech
 * @property int $position
 * @property Carbon $created_at
 * @property-read DiscussionPost $post
 */
#[Fillable(['discussion_post_id', 'mime_type', 'data', 'thumbnail', 'width', 'height', 'size_bytes', 'position'])]
#[Table(name: 'discussion_post_photos', key: 'id')]
class DiscussionPostPhoto extends Model
{
    public const UPDATED_AT = null;

    /**
     * Binární sloupce nikdy neserializujeme (toArray/toJson) – jsou velké a
     * patří jen do servírovací odpovědi.
     *
     * @var list<string>
     */
    protected $hidden = ['data', 'thumbnail'];

    /** @return BelongsTo<DiscussionPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(DiscussionPost::class, 'discussion_post_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'discussion_post_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'position' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unveränderlicher Protokolleintrag: wer hat wann was getan.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $summary
 * @property CarbonImmutable $created_at
 * @property-read User|null $actor
 */
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(?User $actor, string $action, ?Model $subject, string $summary): self
    {
        return self::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'summary' => mb_substr($summary, 0, 255),
        ]);
    }
}

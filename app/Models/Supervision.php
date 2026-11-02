<?php

namespace App\Models;

use App\Domain\TimeWindow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eingeteilte Standaufsicht für einen Schießtag (§ 27 WaffG, §§ 10, 11 AWaffV).
 *
 * @property int $id
 * @property int $shooting_day_id
 * @property int $user_id
 * @property int|null $range_id
 * @property string $starts_at
 * @property string $ends_at
 * @property int|null $assigned_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShootingDay $shootingDay
 * @property-read User $user
 * @property-read Range|null $range
 */
#[Fillable(['shooting_day_id', 'user_id', 'range_id', 'starts_at', 'ends_at', 'assigned_by_id'])]
class Supervision extends Model
{
    /**
     * @return BelongsTo<ShootingDay, $this>
     */
    public function shootingDay(): BelongsTo
    {
        return $this->belongsTo(ShootingDay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Range, $this>
     */
    public function range(): BelongsTo
    {
        return $this->belongsTo(Range::class);
    }

    public function window(): TimeWindow
    {
        return TimeWindow::fromStrings($this->starts_at, $this->ends_at);
    }

    /**
     * Gilt für den Stand: eigene Aufsicht oder Aufsicht für die ganze Anlage.
     */
    public function covers(Range $range): bool
    {
        return $this->range_id === null || $this->range_id === $range->id;
    }
}

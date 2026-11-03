<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShootingDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Termin, an dem geschossen werden darf.
 *
 * @property int $id
 * @property CarbonImmutable $date
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $title
 * @property string|null $notes
 * @property bool $guests_allowed
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['date', 'starts_at', 'ends_at', 'title', 'notes', 'guests_allowed'])]
class ShootingDay extends Model
{
    /** @use HasFactory<ShootingDayFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'guests_allowed' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'guests_allowed' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Range, $this>
     */
    public function ranges(): BelongsToMany
    {
        return $this->belongsToMany(Range::class);
    }

    /**
     * @return HasMany<Supervision, $this>
     */
    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->date->copy()->setTimeFromTimeString($this->starts_at);
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->date->copy()->setTimeFromTimeString($this->ends_at);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isOver(): bool
    {
        return $this->endsAt()->isPast();
    }

    /**
     * @param  Builder<ShootingDay>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('date', '>=', today())->orderBy('date')->orderBy('starts_at');
    }
}

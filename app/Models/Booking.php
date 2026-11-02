<?php

namespace App\Models;

use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shooting_day_id
 * @property int $range_id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property int|null $host_id
 * @property string $starts_at
 * @property string $ends_at
 * @property WeaponType $weapon_type
 * @property bool $club_weapon
 * @property BookingStatus $status
 * @property CarbonImmutable|null $arrived_at
 * @property CarbonImmutable|null $checked_in_at
 * @property int|null $checked_in_by_id
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $booked_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ShootingDay $shootingDay
 * @property-read Range $range
 * @property-read User|null $user
 * @property-read User|null $host
 * @property-read User|null $checkedInBy
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Status und Check-in werden nur über die Buchungs- und Aufsichtslogik gesetzt.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'status', 'arrived_at', 'checked_in_at', 'checked_in_by_id', 'cancelled_at'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'booked',
        'club_weapon' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weapon_type' => WeaponType::class,
            'status' => BookingStatus::class,
            'club_weapon' => 'boolean',
            'arrived_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ShootingDay, $this>
     */
    public function shootingDay(): BelongsTo
    {
        return $this->belongsTo(ShootingDay::class);
    }

    /**
     * @return BelongsTo<Range, $this>
     */
    public function range(): BelongsTo
    {
        return $this->belongsTo(Range::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_id');
    }

    public function window(): TimeWindow
    {
        return TimeWindow::fromStrings($this->starts_at, $this->ends_at);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function shooterName(): string
    {
        return $this->user->name ?? (string) $this->guest_name;
    }

    public function startsAtDateTime(): CarbonImmutable
    {
        return $this->shootingDay->date->copy()->setTimeFromTimeString($this->starts_at);
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopeOccupying(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::occupying());
    }
}

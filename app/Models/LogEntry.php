<?php

namespace App\Models;

use App\Enums\LogSource;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Eintrag in der Schießkladde des Vereins: wer hat wann mit welcher Kategorie geschossen,
 * eingetragen (und damit bestätigt) von einer registrierten Aufsicht oder als Nachtrag.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property CarbonImmutable $shot_on
 * @property WeaponClass $weapon_class
 * @property WeaponOwner|null $weapon_owner
 * @property string|null $discipline
 * @property int $recorded_by_id
 * @property LogSource $source
 * @property string|null $late_reason
 * @property int|null $booking_id
 * @property string $status
 * @property string|null $cancel_reason
 * @property int|null $cancelled_by_id
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User|null $user
 * @property-read User $recordedBy
 * @property-read User|null $cancelledBy
 */
class LogEntry extends Model
{
    /** Nur diese Felder dürfen sich nach dem Anlegen ändern (Storno). */
    private const CANCEL_FIELDS = ['status', 'cancel_reason', 'cancelled_by_id', 'cancelled_at', 'updated_at', 'active_key'];

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'status', 'cancel_reason', 'cancelled_by_id', 'cancelled_at', 'active_key'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            $changed = array_diff(array_keys($entry->getDirty()), self::CANCEL_FIELDS);

            if ($changed !== [] || $entry->getOriginal('status') !== 'active') {
                throw new LogicException('Kladde-Einträge werden nicht geändert, nur storniert.');
            }
        });
        static::deleting(fn () => throw new LogicException('Kladde-Einträge werden nicht gelöscht, nur storniert.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shot_on' => 'date',
            'weapon_class' => WeaponClass::class,
            'weapon_owner' => WeaponOwner::class,
            'source' => LogSource::class,
            'cancelled_at' => 'datetime',
        ];
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
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function shooterName(): string
    {
        return $this->user->name ?? (string) $this->guest_name;
    }

    /**
     * @param  Builder<LogEntry>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}

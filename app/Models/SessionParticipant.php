<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Von der Aufsicht vor Ort aktivierte Person: Mitglied oder Gast. Wird nicht geändert, nur entfernt,
 * solange die Session offen ist und es keine Einträge gibt (Trigger `session_participants_*`).
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property int|null $booking_id
 * @property CarbonImmutable $created_at aktiviert um
 * @property CarbonImmutable $updated_at
 * @property-read ShootingSession $session
 * @property-read User|null $user
 * @property-read Booking|null $booking
 * @property-read Collection<int, SessionEntry> $entries
 */
class SessionParticipant extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (self $participant) {
            $dirty = $participant->getDirty();

            if (array_keys($dirty) !== ['booking_id'] || $dirty['booking_id'] !== null) {
                throw new LogicException('Teilnahmen werden nicht geändert.');
            }
        });
    }

    /**
     * @return BelongsTo<ShootingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ShootingSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<SessionEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SessionEntry::class, 'participant_id');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function name(): string
    {
        return $this->user->name ?? (string) $this->guest_name;
    }
}

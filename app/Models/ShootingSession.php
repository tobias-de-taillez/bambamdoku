<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use LogicException;

/**
 * Schieß-Session einer Aufsicht an einem Tag. Hängt an Aufsicht und Tag, nicht an einer Buchung (E13).
 * Nach dem Abzeichnen endgültig (Trigger `shooting_sessions_final`).
 *
 * @property int $id
 * @property int $supervisor_id die einzige Person, die abzeichnet
 * @property CarbonImmutable $shot_on
 * @property SessionStatus $status
 * @property CarbonImmutable|null $signed_at
 * @property string|null $kiosk_token_hash
 * @property CarbonImmutable $created_at gestartet um
 * @property CarbonImmutable $updated_at
 * @property-read User $supervisor
 * @property-read Collection<int, SessionParticipant> $participants
 * @property-read Collection<int, SessionEntry> $entries
 */
class ShootingSession extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'status', 'signed_at', 'kiosk_token_hash', 'open_key'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $session) {
            if ($session->getOriginal('status') !== SessionStatus::Open) {
                throw new LogicException('Abgezeichnete Sessions sind endgültig.');
            }

            if (array_intersect(array_keys($session->getDirty()), ['supervisor_id', 'shot_on', 'created_at']) !== []) {
                throw new LogicException('Aufsicht und Tag einer Session stehen fest.');
            }
        });
        static::deleting(fn () => throw new LogicException('Sessions werden nicht gelöscht.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shot_on' => 'date',
            'status' => SessionStatus::class,
            'signed_at' => 'datetime',
        ];
    }

    /**
     * Immer als reines Datum speichern (Y-m-d), damit Index und Kladde gleiche Tage erkennen.
     */
    public function setShotOnAttribute(mixed $value): void
    {
        $this->attributes['shot_on'] = CarbonImmutable::parse($value)->toDateString();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * @return HasMany<SessionParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class, 'session_id');
    }

    /**
     * @return HasManyThrough<SessionEntry, SessionParticipant, $this>
     */
    public function entries(): HasManyThrough
    {
        return $this->hasManyThrough(SessionEntry::class, SessionParticipant::class, 'session_id', 'participant_id');
    }

    public function isOpen(): bool
    {
        return $this->status === SessionStatus::Open;
    }

    /**
     * Offen und vom heutigen Tag: nur dann wird erfasst, abgelehnt und abgezeichnet.
     */
    public function isOpenToday(): bool
    {
        return $this->isOpen() && $this->shot_on->isToday();
    }

    /**
     * Nicht abgezeichnet und der Tag ist vorbei: ohne Nachweis, zählen kann nur ein Nachtrag.
     */
    public function isExpired(): bool
    {
        return $this->isOpen() && $this->shot_on->lt(CarbonImmutable::today());
    }

    /**
     * @param  Builder<ShootingSession>  $query
     */
    public function scopeOpenToday(Builder $query): void
    {
        $query->where('status', SessionStatus::Open->value)->whereDate('shot_on', CarbonImmutable::today());
    }
}

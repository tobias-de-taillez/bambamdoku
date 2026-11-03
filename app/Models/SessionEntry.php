<?php

namespace App\Models;

use App\Enums\EntryVia;
use App\Enums\SessionEntryStatus;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Eintrag in der Vorstufe, ohne Nachweiswert. Wird nicht bearbeitet, nur zurückgezogen, abgelehnt
 * oder beim Abzeichnen in die Kladde übernommen (Trigger `session_entries_final`).
 *
 * In der Datenbank steht '' statt NULL (Unique-Index gleich auf SQLite und MySQL); das Model bildet
 * '' auf null ab.
 *
 * @property int $id
 * @property int $participant_id
 * @property string|null $discipline
 * @property WeaponClass $weapon_class
 * @property WeaponOwner|null $weapon_owner
 * @property int|null $shots
 * @property EntryVia $via
 * @property int|null $entered_by_id
 * @property SessionEntryStatus $status
 * @property string|null $reject_reason
 * @property int|null $rejected_by_id
 * @property CarbonImmutable $created_at erfasst um
 * @property CarbonImmutable $updated_at
 * @property-read SessionParticipant $participant
 * @property-read User|null $enteredBy
 * @property-read User|null $rejectedBy
 * @property-read LogEntry|null $logEntry
 */
class SessionEntry extends Model
{
    /** Nur diese Felder ändern sich nach dem Anlegen (Statuswechsel). */
    private const STATUS_FIELDS = ['status', 'reject_reason', 'rejected_by_id', 'updated_at'];

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'status', 'reject_reason', 'rejected_by_id', 'open_key'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'discipline' => '',
        'weapon_owner' => '',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            $changed = array_diff(array_keys($entry->getDirty()), self::STATUS_FIELDS);

            if ($changed !== [] || $entry->getOriginal('status') !== SessionEntryStatus::Pending) {
                throw new LogicException('Einträge werden nicht bearbeitet, nur zurückgezogen oder abgelehnt.');
            }
        });
        static::deleting(fn () => throw new LogicException('Einträge werden nicht gelöscht.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weapon_class' => WeaponClass::class,
            'via' => EntryVia::class,
            'status' => SessionEntryStatus::class,
            'shots' => 'integer',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function discipline(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === '' ? null : $value,
            set: fn (?string $value) => (string) $value,
        );
    }

    /**
     * @return Attribute<WeaponOwner|null, WeaponOwner|string|null>
     */
    protected function weaponOwner(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null || $value === '' ? null : WeaponOwner::from($value),
            set: fn (WeaponOwner|string|null $value) => $value instanceof WeaponOwner ? $value->value : (string) $value,
        );
    }

    /**
     * @return BelongsTo<SessionParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(SessionParticipant::class, 'participant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /**
     * Die Kladde-Zeile, die beim Abzeichnen entstanden ist (fehlt im Race mit einer gleichen Zeile).
     *
     * @return HasOne<LogEntry, $this>
     */
    public function logEntry(): HasOne
    {
        return $this->hasOne(LogEntry::class, 'session_entry_id');
    }

    public function isPending(): bool
    {
        return $this->status === SessionEntryStatus::Pending;
    }
}

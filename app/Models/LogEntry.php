<?php

namespace App\Models;

use App\Enums\EntryVia;
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
 * bestätigt von einer registrierten Aufsicht (Abzeichnen der Session, im Altbestand Eintragen
 * am Stand) oder als Nachtrag bzw. Korrektur des Vorstands.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property CarbonImmutable $shot_on
 * @property WeaponClass $weapon_class
 * @property WeaponOwner|null $weapon_owner
 * @property string|null $discipline
 * @property int $recorded_by_id bestätigt von: abzeichnende Aufsicht, Altbestand eintragende Aufsicht, sonst Admin
 * @property LogSource $source
 * @property string|null $late_reason
 * @property int|null $booking_id
 * @property int|null $shots nie im Schlüssel, nie in der Zählung
 * @property int|null $session_entry_id Herkunft aus der Vorstufe
 * @property int|null $corrects_id korrigierte Zeile (Korrektur durch den Admin)
 * @property string $status
 * @property string|null $cancel_reason
 * @property int|null $cancelled_by_id
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User|null $user
 * @property-read User $recordedBy
 * @property-read User|null $cancelledBy
 * @property-read SessionEntry|null $sessionEntry
 * @property-read LogEntry|null $corrects
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
            'shots' => 'integer',
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

    /**
     * @return BelongsTo<SessionEntry, $this>
     */
    public function sessionEntry(): BelongsTo
    {
        return $this->belongsTo(SessionEntry::class);
    }

    /**
     * @return BelongsTo<LogEntry, $this>
     */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(LogEntry::class, 'corrects_id');
    }

    /**
     * Immer als reines Datum speichern (Y-m-d), damit der Unique-Index gleiche Tage erkennt.
     */
    public function setShotOnAttribute(mixed $value): void
    {
        $this->attributes['shot_on'] = CarbonImmutable::parse($value)->toDateString();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Die Ursprungszeile einer Korrektur (über alle Korrekturen hinweg); sonst die Zeile selbst.
     */
    public function origin(): self
    {
        $row = $this;

        for ($depth = 0; $depth < 50 && $row->corrects_id !== null && $row->corrects !== null; $depth++) {
            $row = $row->corrects;
        }

        return $row;
    }

    /**
     * Altbestand aus dem Backfill (…_000900): Bestätiger = Mitglied selbst, also ohne Bestätigung durch eine Aufsicht.
     */
    public function isFromBooking(): bool
    {
        return $this->source === LogSource::Stand && $this->user_id !== null && $this->recorded_by_id === $this->user_id;
    }

    /**
     * „erfasst über“: Mitglied (eigenes Gerät) / Kiosk / Aufsicht / Stand bis zur Umstellung / Nachtrag / Korrektur.
     */
    public function viaLabel(): string
    {
        if ($this->corrects_id !== null) {
            $origin = $this->origin();

            return $origin->is($this) ? 'Korrektur' : 'Korrektur; ursprünglich '.$origin->viaLabel();
        }

        return match ($this->source) {
            LogSource::Session => match ($this->sessionEntry?->via) {
                EntryVia::OwnDevice => 'Mitglied (eigenes Gerät)',
                EntryVia::Kiosk => 'Kiosk',
                default => 'Aufsicht',
            },
            LogSource::Stand => $this->isFromBooking() ? 'aus Buchung übernommen' : 'Stand bis zur Umstellung',
            LogSource::LateEntry => 'Nachtrag',
        };
    }

    /**
     * „bestätigt von“: die Aufsicht; bei Nachtrag der Admin mit Grund; bei Korrektur die Aufsicht
     * der Ursprungszeile und der korrigierende Admin mit Grund.
     */
    public function confirmedByLabel(): string
    {
        if ($this->corrects_id !== null) {
            $origin = $this->origin();
            $base = $origin->is($this) ? '' : $origin->confirmedByLabel().', ';

            return sprintf('%skorrigiert von %s: %s', $base, $this->recordedBy->name, (string) $this->late_reason);
        }

        if ($this->isFromBooking()) {
            return 'aus Buchung übernommen, ohne Bestätigung durch eine Aufsicht';
        }

        if ($this->source === LogSource::LateEntry) {
            return sprintf('%s, Nachtrag: %s', $this->recordedBy->name, (string) $this->late_reason);
        }

        return $this->recordedBy->name;
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

<?php

namespace App\Models;

use App\Enums\SupervisionScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV). Nur anhängen, nie ändern.
 *
 * @property int $id
 * @property int $user_id
 * @property SupervisionScope $scope
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable $checked_on
 * @property int|null $checked_by_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property-read User $user
 * @property-read User|null $checkedBy
 */
class Qualification extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Das Aufsichtsregister wird nur ergänzt, nicht geändert.'));
        static::deleting(fn () => throw new LogicException('Das Aufsichtsregister wird nur ergänzt, nicht gelöscht.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => SupervisionScope::class,
            'valid_until' => 'date',
            'checked_on' => 'date',
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
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    /**
     * Neue Registerzeile anlegen und den Zwischenspeicher am Mitglied nachziehen.
     */
    public static function register(
        User $user,
        SupervisionScope $scope,
        ?string $validUntil,
        string $checkedOn,
        ?User $checkedBy,
        ?string $note = null,
    ): self {
        return DB::transaction(function () use ($user, $scope, $validUntil, $checkedOn, $checkedBy, $note) {
            $row = self::create([
                'user_id' => $user->id,
                'scope' => $scope,
                'valid_until' => $scope === SupervisionScope::Revoked ? null : $validUntil,
                'checked_on' => $checkedOn,
                'checked_by_id' => $checkedBy?->id,
                'note' => $note,
            ]);

            $user->forceFill([
                'supervisor_until' => $scope === SupervisionScope::Revoked ? null : $validUntil,
                'supervisor_scope' => $scope === SupervisionScope::Revoked ? null : $scope->value,
            ])->save();

            AuditEntry::record($checkedBy, 'qualification.registered', $user, sprintf(
                'Aufsichtsregister %s: %s%s',
                $user->name,
                $scope->label(),
                $scope === SupervisionScope::Revoked || $validUntil === null ? '' : ', gültig bis '.CarbonImmutable::parse($validUntil)->format('d.m.Y'),
            ));

            return $row;
        });
    }
}

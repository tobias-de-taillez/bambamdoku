<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\SupervisionScope;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property bool $active
 * @property string|null $member_number
 * @property CarbonImmutable|null $member_since
 * @property string|null $calendar_token
 * @property CarbonImmutable|null $supervisor_until abgeleitet aus dem Aufsichtsregister
 * @property SupervisionScope|null $supervisor_scope abgeleitet aus dem Aufsichtsregister
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'calendar_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Gleiche Standardwerte wie in der Datenbank, damit neue Instanzen sie schon vor dem Speichern haben.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'member',
        'active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
            'supervisor_until' => 'date',
            'supervisor_scope' => SupervisionScope::class,
            'member_since' => 'date',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Hat am angegebenen Tag eine gültige Zeile im Aufsichtsregister;
     * mit $licensed zusätzlich den Umfang für erlaubnispflichtige Waffen.
     */
    public function canSupervise(?CarbonImmutable $on = null, bool $licensed = false): bool
    {
        return $this->active
            && $this->supervisor_until !== null
            && $this->supervisor_scope !== null
            && (! $licensed || $this->supervisor_scope === SupervisionScope::Licensed)
            && $this->supervisor_until->endOfDay()->gte($on ?? now());
    }

    /**
     * @return HasMany<Qualification, $this>
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class)->latest('id');
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }
}

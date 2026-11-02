<?php

namespace App\Models;

use Database\Factories\ShootingDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Ein Termin, an dem geschossen werden darf.
 *
 * @property int $id
 * @property string|null $series
 * @property Carbon $date
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $title
 * @property string|null $notes
 * @property bool $restricted_to_groups
 * @property bool $guests_allowed
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['series', 'date', 'starts_at', 'ends_at', 'title', 'notes', 'restricted_to_groups', 'guests_allowed'])]
class ShootingDay extends Model
{
    /** @use HasFactory<ShootingDayFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'restricted_to_groups' => false,
        'guests_allowed' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'restricted_to_groups' => 'boolean',
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
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function startsAt(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->starts_at);
    }

    public function endsAt(): Carbon
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
     * Ob ein Mitglied diesen Tag sehen und buchen darf.
     */
    public function isOpenFor(User $user): bool
    {
        if ($user->isAdmin() || ! $this->restricted_to_groups) {
            return true;
        }

        return $this->groups->pluck('id')->intersect($user->groups->pluck('id'))->isNotEmpty();
    }

    /**
     * @param  Builder<ShootingDay>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('date', '>=', today())->orderBy('date')->orderBy('starts_at');
    }

    /**
     * @param  Builder<ShootingDay>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $groupIds = $user->groups()->pluck('groups.id');

        $query->where(fn (Builder $query) => $query
            ->where('restricted_to_groups', false)
            ->orWhereHas('groups', fn (Builder $query) => $query->whereIn('groups.id', $groupIds)));
    }
}

<?php

namespace App\Models;

use App\Enums\WeaponType;
use Database\Factories\RangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ein Stand der Schießanlage, z. B. "25 m Kurzwaffe" mit 6 Bahnen.
 *
 * @property int $id
 * @property string $name
 * @property int $lanes
 * @property Collection<int, WeaponType> $weapon_types
 * @property string|null $notes
 * @property int $position
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'lanes', 'weapon_types', 'notes', 'position', 'active'])]
class Range extends Model
{
    /** @use HasFactory<RangeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lanes' => 'integer',
            'position' => 'integer',
            'active' => 'boolean',
            'weapon_types' => AsEnumCollection::of(WeaponType::class),
        ];
    }

    /**
     * @param  Builder<Range>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }
}

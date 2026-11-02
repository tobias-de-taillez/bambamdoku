<?php

namespace Database\Factories;

use App\Enums\WeaponType;
use App\Models\Range;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Range>
 */
class RangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '25 m Kurzwaffe',
            'lanes' => 5,
            'weapon_types' => [WeaponType::Pistol],
            'active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\WeaponType;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'weapon_type' => WeaponType::Pistol,
            'club_weapon' => false,
        ];
    }
}

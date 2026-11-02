<?php

namespace Database\Factories;

use App\Models\Range;
use App\Models\ShootingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShootingDay>
 */
class ShootingDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->addDays(3)->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ];
    }

    public function withRange(?Range $range = null): static
    {
        return $this->afterCreating(fn (ShootingDay $day) => $day->ranges()->attach($range ?? Range::factory()->create()));
    }
}

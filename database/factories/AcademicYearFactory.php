<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->unique()->numberBetween(2000, 2099);

        return [
            'name' => $start.'/'.($start + 1),
            'is_active' => false,
            'started_on' => fake()->dateTimeBetween("$start-07-01", "$start-07-31"),
            'ended_on' => fake()->dateTimeBetween(($start + 1).'-06-01', ($start + 1).'-06-30'),
        ];
    }

    /**
     * Indicate that the academic year is the active one.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => true]);
    }
}

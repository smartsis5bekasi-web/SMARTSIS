<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'nis' => fake()->unique()->numerify('##########'),
            'nisn' => fake()->unique()->numerify('##########'),
            'name' => fake()->name(),
            'gender' => fake()->randomElement(['L', 'P']),
            'birth_date' => fake()->dateTimeBetween('-19 years', '-15 years')->format('Y-m-d'),
            'address' => fake()->address(),
            'classroom_id' => null,
            'teacher_id' => null,
            'major_id' => null,
            'year_in' => fake()->numberBetween(2022, 2026),
            'current_point' => 100,
        ];
    }

    /**
     * Student who has finished the first-login onboarding (face registered + confirmed).
     */
    public function onboarded(): static
    {
        return $this->state(fn (): array => [
            'face_descriptors' => [array_map(fn (): float => fake()->randomFloat(6, -1, 1), range(1, 128))],
            'face_registered_at' => now(),
            'onboarded_at' => now(),
        ]);
    }
}

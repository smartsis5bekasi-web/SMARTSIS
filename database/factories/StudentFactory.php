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
}

<?php

namespace Database\Factories;

use App\Enums\WarningLevel;
use App\Enums\WarningStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\WarningLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarningLetter>
 */
class WarningLetterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'level' => WarningLevel::Sp1,
            'status' => WarningStatus::Pending,
            'points_snapshot' => 78,
            'threshold' => 80,
            'letter_number' => null,
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => WarningStatus::Approved,
            'letter_number' => fake()->unique()->numerify('0##').'/SP1/VII/'.now()->year,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function ofLevel(WarningLevel $level): static
    {
        return $this->state(fn (): array => ['level' => $level]);
    }
}

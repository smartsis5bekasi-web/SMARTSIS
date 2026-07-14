<?php

namespace Database\Factories;

use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Models\Permit;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permit>
 */
class PermitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'type' => fake()->randomElement(PermitType::cases()),
            'date' => now()->toDateString(),
            'reason' => fake()->sentence(),
            'attachment_path' => null,
            'status' => PermitStatus::Pending,
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PermitStatus::Approved,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => PermitStatus::Rejected,
            'decided_by' => User::factory(),
            'decided_at' => now(),
            'decision_note' => fake()->sentence(),
        ]);
    }

    public function ofType(PermitType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}

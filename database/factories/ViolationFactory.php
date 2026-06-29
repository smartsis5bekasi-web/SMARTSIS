<?php

namespace Database\Factories;

use App\Enums\PointApprovalStatus;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Violation>
 */
class ViolationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'point_rule_id' => PointRule::factory()->deduction(),
            'reported_by' => null,
            'verified_by' => null,
            'status' => PointApprovalStatus::Pending,
            'chronology' => fake()->sentence(),
            'evidence_path' => null,
            'occurred_on' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => PointApprovalStatus::Approved]);
    }
}

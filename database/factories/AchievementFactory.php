<?php

namespace Database\Factories;

use App\Enums\PointApprovalStatus;
use App\Models\Achievement;
use App\Models\PointRule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'point_rule_id' => PointRule::factory()->addition(),
            'title' => fake()->sentence(3),
            'input_by' => null,
            'verified_by' => null,
            'status' => PointApprovalStatus::Pending,
            'level' => fake()->randomElement(['Sekolah', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional']),
            'description' => fake()->optional()->paragraph(),
            'note' => null,
            'evidence_path' => null,
            'achieved_on' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => PointApprovalStatus::Approved]);
    }
}

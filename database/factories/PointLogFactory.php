<?php

namespace Database\Factories;

use App\Enums\PointType;
use App\Models\PointLog;
use App\Models\PointRule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointLog>
 */
class PointLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(PointType::cases());
        $delta = $type->sign() * fake()->numberBetween(1, 30);

        return [
            'student_id' => Student::factory(),
            'point_rule_id' => PointRule::factory(),
            'type' => $type,
            'source_type' => null,
            'source_id' => null,
            'delta' => $delta,
            'balance_after' => fake()->numberBetween(0, 100),
            'note' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }

    public function addition(): static
    {
        return $this->state(fn (): array => [
            'type' => PointType::Addition,
            'delta' => fake()->numberBetween(1, 30),
        ]);
    }

    public function deduction(): static
    {
        return $this->state(fn (): array => [
            'type' => PointType::Deduction,
            'delta' => -fake()->numberBetween(1, 30),
        ]);
    }
}

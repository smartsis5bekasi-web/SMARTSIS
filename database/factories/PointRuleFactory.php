<?php

namespace Database\Factories;

use App\Enums\PointSource;
use App\Enums\PointType;
use App\Models\PointRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointRule>
 */
class PointRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => PointType::Deduction,
            'source' => PointSource::Violation,
            'point' => fake()->numberBetween(1, 30),
            'is_active' => true,
        ];
    }

    /**
     * A point-adding rule sourced from achievements.
     */
    public function addition(): static
    {
        return $this->state(fn (): array => [
            'type' => PointType::Addition,
            'source' => PointSource::Achievement,
        ]);
    }

    /**
     * A point-subtracting rule sourced from violations.
     */
    public function deduction(): static
    {
        return $this->state(fn (): array => [
            'type' => PointType::Deduction,
            'source' => PointSource::Violation,
        ]);
    }

    /**
     * An inactive rule (hidden from selection).
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}

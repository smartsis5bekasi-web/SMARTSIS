<?php

namespace Database\Factories;

use App\Enums\CounselingAction;
use App\Models\CounselingRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CounselingRecord>
 */
class CounselingRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'action' => CounselingAction::Konseling,
            'called_on' => now()->toDateString(),
            'note' => fake()->sentence(),
            'recorded_by' => User::factory(),
        ];
    }

    public function ofAction(CounselingAction $action): static
    {
        return $this->state(fn (): array => ['action' => $action]);
    }
}

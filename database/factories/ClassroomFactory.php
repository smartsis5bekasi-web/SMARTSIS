<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Major;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->randomElement(GradeLevel::cases());

        return [
            'name' => $grade->numeral().' '.fake()->randomElement(['IPA 1', 'IPA 2', 'IPS 1', 'IPS 2']),
            'grade' => $grade,
            'major_id' => Major::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'homeroom_teacher_id' => null,
        ];
    }

    /**
     * A classroom of the given tingkat.
     */
    public function grade(GradeLevel $grade): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $grade->numeral().' '.str($attributes['name'])->after(' '),
            'grade' => $grade,
        ]);
    }
}

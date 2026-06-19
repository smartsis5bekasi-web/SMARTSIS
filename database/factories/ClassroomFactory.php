<?php

namespace Database\Factories;

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
        $grade = fake()->randomElement(['X', 'XI', 'XII']);

        return [
            'name' => $grade.' '.fake()->randomElement(['IPA 1', 'IPA 2', 'IPS 1', 'IPS 2']),
            'major_id' => Major::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'homeroom_teacher_id' => null,
        ];
    }
}

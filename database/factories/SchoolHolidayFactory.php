<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Models\SchoolHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SchoolHoliday>
 */
class SchoolHolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = Carbon::instance(fake()->dateTimeBetween('now', '+3 months'))->startOfDay();

        return [
            'name' => fake()->randomElement(['Cuti Bersama', 'Libur Nasional', 'Libur Darurat', 'Kegiatan Sekolah']),
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'grades' => null,
            'created_by' => null,
        ];
    }

    /**
     * A holiday on the given day (or range of days).
     */
    public function on(string $startDate, ?string $endDate = null): static
    {
        return $this->state(fn (): array => [
            'start_date' => $startDate,
            'end_date' => $endDate ?? $startDate,
        ]);
    }

    /**
     * A holiday for some grades only; everyone else attends as usual.
     */
    public function forGrades(GradeLevel ...$grades): static
    {
        return $this->state(fn (): array => [
            'grades' => array_map(fn (GradeLevel $grade): int => $grade->value, $grades),
        ]);
    }
}

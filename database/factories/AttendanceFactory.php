<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Hadir,
            'point_rule_id' => null,
            'checked_in_at' => now()->setTime(6, 45),
            'checked_out_at' => null,
            'method' => 'face',
            'recorded_by' => null,
            'note' => null,
        ];
    }

    public function late(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceStatus::Terlambat,
            'checked_in_at' => now()->setTime(7, 30),
        ]);
    }

    public function alpha(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceStatus::Alpha,
            'checked_in_at' => null,
            'method' => 'manual',
        ]);
    }

    public function izin(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceStatus::Izin,
            'checked_in_at' => null,
            'method' => 'manual',
        ]);
    }

    public function sakit(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceStatus::Sakit,
            'checked_in_at' => null,
            'method' => 'manual',
        ]);
    }

    public function checkedOut(): static
    {
        return $this->state(fn (): array => [
            'checked_out_at' => now()->setTime(15, 10),
        ]);
    }
}

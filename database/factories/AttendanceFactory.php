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
            'attachment_path' => null,
            'reason' => null,
            // A camera scan is its own proof; self-declared statuses override
            // this in their own states.
            'verified_at' => now(),
            'verified_by' => null,
        ];
    }

    /**
     * A record carrying the evidence the self-service camera collects: the
     * selfie frame and the GPS fix.
     */
    public function captured(): static
    {
        return $this->state(fn (): array => [
            'method' => 'self',
            'check_in_photo_path' => '/storage/attendances/sample-in.jpg',
            'check_in_latitude' => -6.2607330,
            'check_in_longitude' => 106.7810400,
            'check_in_accuracy' => 12,
        ]);
    }

    /**
     * A self-declared sakit/izin still awaiting a Guru Piket's confirmation.
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'method' => 'self',
            'attachment_path' => '/storage/attendances/proofs/surat.jpg',
            'verified_at' => null,
            'verified_by' => null,
        ]);
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

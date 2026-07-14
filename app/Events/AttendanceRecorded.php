<?php

namespace App\Events;

use App\Events\Contracts\PointSourceVerified;
use App\Models\Attendance;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when an attendance record lands on a penalizing status (terlambat /
 * alpha, PRD F-13). Only dispatched when the record carries a point rule, so
 * the point engine can deduct automatically.
 */
class AttendanceRecorded implements PointSourceVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Attendance $attendance,
        public User $recorder,
    ) {}

    public function source(): Model
    {
        return $this->attendance;
    }

    public function student(): Student
    {
        return $this->attendance->student;
    }

    public function rule(): PointRule
    {
        return $this->attendance->pointRule;
    }

    public function verifier(): User
    {
        return $this->recorder;
    }

    public function note(): string
    {
        return __('Absensi: :status', ['status' => $this->attendance->status->label()]);
    }
}

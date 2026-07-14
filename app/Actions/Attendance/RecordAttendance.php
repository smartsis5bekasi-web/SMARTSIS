<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\PermitType;
use App\Events\AttendanceRecorded;
use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Permit;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The single entry point for every attendance mutation (PRD F-09/F-10/F-11):
 * face check-in/check-out from the kiosk, manual status corrections, and the
 * end-of-day alpha sweep. Late and alpha statuses drive the point engine
 * through {@see AttendanceRecorded}.
 */
class RecordAttendance
{
    /**
     * Record the morning check-in; the status (Hadir/Terlambat) follows the
     * configured late threshold.
     *
     * @throws AttendanceException when today's attendance is already recorded
     */
    public function checkIn(Student $student, User $by, string $method = 'face'): Attendance
    {
        $now = now();
        $existing = $student->attendances()->onDate($now)->first();

        if ($existing !== null) {
            throw AttendanceException::alreadyRecorded($existing);
        }

        $setting = AttendanceSetting::current();
        $status = $setting->checkInStatus($now);
        $rule = $setting->ruleFor($status);
        $note = null;

        // An approved izin terlambat waives the late penalty for that day;
        // the status stays Terlambat so the monitoring data remains honest.
        if ($status === AttendanceStatus::Terlambat && Permit::approvedFor($student, PermitType::Terlambat, $now)) {
            $rule = null;
            $note = __('Izin terlambat disetujui — tanpa pengurangan poin.');
        }

        $attendance = $student->attendances()->create([
            'date' => $now->toDateString(),
            'status' => $status,
            'point_rule_id' => $rule?->id,
            'checked_in_at' => $now,
            'method' => $method,
            'recorded_by' => $by->id,
            'note' => $note,
        ]);

        if ($rule !== null) {
            AttendanceRecorded::dispatch($attendance, $by);
        }

        return $attendance;
    }

    /**
     * Record the end-of-day check-out on top of today's check-in.
     *
     * @throws AttendanceException when there is nothing to check out from
     */
    public function checkOut(Student $student, User $by): Attendance
    {
        $now = now();
        $attendance = $student->attendances()->onDate($now)->first();

        if ($attendance === null || ! $attendance->status->isPresent()) {
            throw AttendanceException::notCheckedIn();
        }

        if ($attendance->isCheckedOut()) {
            throw AttendanceException::alreadyCheckedOut();
        }

        $setting = AttendanceSetting::current();

        if (! $setting->isCheckOutOpen($now)) {
            // An approved izin pulang awal opens check-out early for that day.
            if (! Permit::approvedFor($student, PermitType::PulangAwal, $now)) {
                throw AttendanceException::checkOutNotOpen(substr($setting->check_out_after, 0, 5));
            }

            $attendance->update([
                'checked_out_at' => $now,
                'note' => trim(($attendance->note !== null ? $attendance->note.' · ' : '').__('Pulang awal (izin disetujui).')),
            ]);

            return $attendance;
        }

        $attendance->update(['checked_out_at' => $now]);

        return $attendance;
    }

    /**
     * Manually set a student's status for a date (izin/sakit/alpha or a
     * correction). Reverses a previously applied penalty before applying the
     * rule that matches the new status, so corrections never double-count.
     */
    public function markStatus(
        Student $student,
        AttendanceStatus $status,
        User $by,
        ?CarbonInterface $date = null,
        ?string $note = null,
    ): Attendance {
        $date ??= now();

        /** @var Attendance $attendance */
        $attendance = $student->attendances()->onDate($date)->first()
            ?? $student->attendances()->make(['date' => $date->toDateString()]);

        if ($attendance->exists && $attendance->status === $status) {
            return $attendance;
        }

        $attendance->reversePoints($by, __('Koreksi absensi menjadi :status', ['status' => $status->label()]));

        $rule = AttendanceSetting::current()->ruleFor($status);

        // Manual corrections honor an approved izin terlambat the same way
        // the kiosk check-in does.
        if ($status === AttendanceStatus::Terlambat && Permit::approvedFor($student, PermitType::Terlambat, $date)) {
            $rule = null;
        }

        $attendance->fill([
            'status' => $status,
            'point_rule_id' => $rule?->id,
            'method' => 'manual',
            'recorded_by' => $by->id,
            'note' => $note,
        ])->save();

        if ($rule !== null) {
            AttendanceRecorded::dispatch($attendance, $by);
        }

        return $attendance;
    }

    /**
     * Mark every student without an attendance record on the date as Alpha
     * (the end-of-day sweep behind "pengurangan poin otomatis: alpha").
     *
     * @return int the number of students marked
     */
    public function markAbsentees(User $by, ?CarbonInterface $date = null): int
    {
        $date ??= now();
        $marked = 0;

        Student::query()
            ->whereDoesntHave('attendances', fn ($query) => $query->whereDate('date', $date->toDateString()))
            ->chunkById(100, function ($students) use ($by, $date, &$marked): void {
                foreach ($students as $student) {
                    $this->markStatus($student, AttendanceStatus::Alpha, $by, $date);
                    $marked++;
                }
            });

        return $marked;
    }
}

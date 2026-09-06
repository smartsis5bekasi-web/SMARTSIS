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
use App\Support\AttendanceCapture;
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
     * @throws AttendanceException when the window has not opened yet, or
     *                             today's attendance is already recorded
     */
    public function checkIn(
        Student $student,
        User $by,
        string $method = 'face',
        ?AttendanceCapture $capture = null,
    ): Attendance {
        $now = now();
        $existing = $student->attendances()->onDate($now)->first();

        if ($existing !== null) {
            throw AttendanceException::alreadyRecorded($existing);
        }

        $setting = AttendanceSetting::current();

        if (! $setting->isCheckInOpen($now)) {
            throw AttendanceException::checkInNotOpen(substr($setting->check_in_start, 0, 5));
        }

        $status = $setting->checkInStatus($now);
        $rule = $setting->ruleFor($status);
        $note = null;

        // An approved izin terlambat waives the late penalty for that day;
        // the status stays Terlambat so the monitoring data remains honest.
        if ($status === AttendanceStatus::Terlambat && Permit::approvedFor($student, PermitType::Terlambat, $now)) {
            $rule = null;
            $note = __('Izin terlambat disetujui — tanpa pengurangan poin.');
        }

        $capture ??= new AttendanceCapture;

        // Absensi no longer verifies a face — the location does. A staffed
        // kiosk is verified by the operator standing there; a student's own
        // scan is verified by the GPS fix it carries, and stays pending
        // without one.
        $verified = $method !== 'self' || $capture->hasFix();

        $attendance = $student->attendances()->create([
            'date' => $now->toDateString(),
            'status' => $status,
            'point_rule_id' => $rule?->id,
            'checked_in_at' => $now,
            'method' => $method,
            'recorded_by' => $by->id,
            'note' => $note,
            'verified_at' => $verified ? $now : null,
            'verified_by' => $verified ? $by->id : null,
            ...$capture->checkInAttributes(),
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
    public function checkOut(Student $student, User $by, ?AttendanceCapture $capture = null): Attendance
    {
        $now = now();
        $capture ??= new AttendanceCapture;
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
                ...$capture->checkOutAttributes(),
                ...$this->lateVerification($attendance, $capture, $by),
            ]);

            return $attendance;
        }

        $attendance->update([
            'checked_out_at' => $now,
            ...$capture->checkOutAttributes(),
            ...$this->lateVerification($attendance, $capture, $by),
        ]);

        return $attendance;
    }

    /**
     * A check-in that arrived without a location leaves the day pending; a
     * check-out that does carry one settles it, so a single denied permission
     * in the morning does not condemn the whole record.
     *
     * @return array{verified_at?: CarbonInterface, verified_by?: int}
     */
    private function lateVerification(Attendance $attendance, AttendanceCapture $capture, User $by): array
    {
        if ($attendance->isVerified() || ! $capture->hasFix()) {
            return [];
        }

        return ['verified_at' => now(), 'verified_by' => $by->id];
    }

    /**
     * Record a student's own Sakit / Izin declaration from the absensi page.
     *
     * These days never check out, so the record is complete the moment it is
     * written; it stays unverified until a Guru Piket / Wali Kelas confirms
     * the uploaded proof.
     *
     * @throws AttendanceException when today is already recorded, or the
     *                             status is not one a student may declare
     */
    public function selfDeclare(
        Student $student,
        AttendanceStatus $status,
        User $by,
        string $attachmentPath,
        ?string $reason = null,
        ?AttendanceCapture $capture = null,
    ): Attendance {
        if (! $status->isSelfDeclarable()) {
            throw AttendanceException::notSelfDeclarable($status);
        }

        $now = now();
        $existing = $student->attendances()->onDate($now)->first();

        if ($existing !== null) {
            throw AttendanceException::alreadyRecorded($existing);
        }

        $capture ??= new AttendanceCapture;

        return $student->attendances()->create([
            'date' => $now->toDateString(),
            'status' => $status,
            'point_rule_id' => AttendanceSetting::current()->ruleFor($status)?->id,
            'method' => 'self',
            'recorded_by' => $by->id,
            'attachment_path' => $attachmentPath,
            'reason' => $reason,
            'verified_at' => null,
            'verified_by' => null,
            'check_in_latitude' => $capture->latitude,
            'check_in_longitude' => $capture->longitude,
            'check_in_accuracy' => $capture->accuracy,
        ]);
    }

    /**
     * Confirm a self-declared sakit/izin after reviewing its attachment.
     */
    public function verify(Attendance $attendance, User $by): Attendance
    {
        if (! $attendance->isVerified()) {
            $attendance->markVerified($by);
        }

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
            // A staff decision is the verification.
            'verified_at' => now(),
            'verified_by' => $by->id,
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

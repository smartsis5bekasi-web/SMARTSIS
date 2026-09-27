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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single entry point for every attendance mutation (PRD F-09/F-10/F-11):
 * face check-in/check-out from the kiosk, manual status corrections, the
 * end-of-day pending sweep, and the alpha sweep. Late and alpha statuses drive
 * the point engine through {@see AttendanceRecorded}.
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
        $existing = $this->unresolvedRecordFor($student, $now);

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

        $attendance = $existing ?? $student->attendances()->make();

        $attendance->fill([
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
        ])->save();

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
        $attendance = $this->unresolvedRecordFor($student, $now) ?? $student->attendances()->make();

        $capture ??= new AttendanceCapture;

        $attendance->fill([
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
        ])->save();

        return $attendance;
    }

    /**
     * Today's record when it is only the sweep's pending placeholder, which a
     * late scan or declaration replaces. Anything else means the day is
     * already settled.
     *
     * @throws AttendanceException when the day already has a real record
     */
    private function unresolvedRecordFor(Student $student, CarbonInterface $date): ?Attendance
    {
        $existing = $student->attendances()->onDate($date)->first();

        if ($existing !== null && ! $existing->status->isPending()) {
            throw AttendanceException::alreadyRecorded($existing);
        }

        return $existing;
    }

    /**
     * Confirm a self-declared sakit/izin after reviewing its attachment.
     */
    public function verify(Attendance $attendance, User $by): Attendance
    {
        // A pending day has nothing to verify — it has to be confirmed as a
        // real status through markStatus() instead.
        if (! $attendance->isVerified() && ! $attendance->status->isPending()) {
            $attendance->markVerified($by);
        }

        return $attendance;
    }

    /**
     * Manually set a student's status for a date (izin/sakit/alpha or a
     * correction). Reverses a previously applied penalty before applying the
     * rule that matches the new status, so corrections never double-count —
     * including taking back the points of an automatic Alpha.
     */
    public function markStatus(
        Student $student,
        AttendanceStatus $status,
        User $by,
        ?CarbonInterface $date = null,
        ?string $note = null,
    ): Attendance {
        if ($status->isPending()) {
            throw AttendanceException::notAssignable($status);
        }

        $date ??= now();

        /** @var Attendance $attendance */
        $attendance = $student->attendances()->onDate($date)->first()
            ?? $student->attendances()->make(['date' => $date->toDateString()]);

        if ($attendance->exists && $attendance->status === $status) {
            return $attendance;
        }

        return $this->settle($attendance, $status, $by, $date, [
            'method' => 'manual',
            'recorded_by' => $by->id,
            'note' => $note,
            // A staff decision is the verification, and replaces any
            // automatic Alpha the system applied earlier.
            'verified_at' => now(),
            'verified_by' => $by->id,
            'escalated_at' => null,
        ]);
    }

    /**
     * Turn every "Menunggu Konfirmasi" day whose confirmation window has run
     * out into Alpha, applying the alpha point rule. The record is labelled
     * "Alpha otomatis" and stays unverified, so a teacher can still correct
     * it later — {@see markStatus()} then gives the points back.
     *
     * @return int the number of days escalated
     */
    public function escalatePending(?CarbonInterface $today = null): int
    {
        $setting = AttendanceSetting::current();

        if (! $setting->autoAlphaEnabled()) {
            return 0;
        }

        $today = Carbon::parse($today ?? now())->startOfDay();
        $escalated = 0;

        Attendance::query()
            ->where('status', AttendanceStatus::Pending)
            // Weekday deadlines are never earlier than calendar ones, so this
            // only narrows the scan; the exact check happens per record.
            ->whereDate('date', '<', $today->copy()->subDays($setting->pending_alpha_after_days)->toDateString())
            ->chunkById(100, function ($records) use ($setting, $today, &$escalated): void {
                foreach ($records as $record) {
                    if ($setting->pendingDeadline($record->date)->gte($today)) {
                        continue;
                    }

                    $escalated += (int) DB::transaction(function () use ($record, $setting): bool {
                        // A teacher may have confirmed it since the chunk was read.
                        $attendance = Attendance::query()->with('student')->lockForUpdate()->find($record->id);

                        if ($attendance === null || ! $attendance->status->isPending()) {
                            return false;
                        }

                        $this->settle($attendance, AttendanceStatus::Alpha, null, $attendance->date, [
                            'method' => 'system',
                            'recorded_by' => null,
                            'note' => __('Alpha otomatis — tidak dikonfirmasi guru dalam :days hari sekolah.', ['days' => $setting->pending_alpha_after_days]),
                            'verified_at' => null,
                            'verified_by' => null,
                            'escalated_at' => now(),
                        ]);

                        return true;
                    });
                }
            });

        return $escalated;
    }

    /**
     * Move a record to a new status: take back whatever its previous status
     * cost, then apply the rule of the new one — atomically, so a failure in
     * between never leaves the balance half-corrected.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function settle(Attendance $attendance, AttendanceStatus $status, ?User $by, CarbonInterface $date, array $attributes): Attendance
    {
        return DB::transaction(function () use ($attendance, $status, $by, $date, $attributes): Attendance {
            $attendance->reversePoints($by, __('Koreksi absensi menjadi :status', ['status' => $status->label()]));

            $rule = AttendanceSetting::current()->ruleFor($status);

            // Manual corrections honor an approved izin terlambat the same way
            // the kiosk check-in does.
            if ($status === AttendanceStatus::Terlambat && Permit::approvedFor($attendance->student, PermitType::Terlambat, $date)) {
                $rule = null;
            }

            $attendance->fill([
                'status' => $status,
                'point_rule_id' => $rule?->id,
                ...$attributes,
            ])->save();

            if ($rule !== null) {
                AttendanceRecorded::dispatch($attendance, $by);
            }

            return $attendance;
        });
    }

    /**
     * Mark every student without a settled record on the date — no record at
     * all, or only the sweep's pending placeholder — as Alpha (the manual
     * "Tandai Alpha" bulk action behind "pengurangan poin otomatis: alpha").
     *
     * @return int the number of students marked
     */
    public function markAbsentees(User $by, ?CarbonInterface $date = null): int
    {
        $date ??= now();
        $marked = 0;

        $this->attendingStudents()
            ->whereDoesntHave('attendances', fn (Builder $query) => $query
                ->whereDate('date', $date->toDateString())
                ->where('status', '!=', AttendanceStatus::Pending))
            ->chunkById(100, function ($students) use ($by, $date, &$marked): void {
                foreach ($students as $student) {
                    $this->markStatus($student, AttendanceStatus::Alpha, $by, $date);
                    $marked++;
                }
            });

        return $marked;
    }

    /**
     * Write a Pending record for every student who ended the date with no
     * attendance at all, so an absence is always on the books for a teacher
     * to confirm as alpha, sakit, or izin. No points move until then.
     *
     * Students registered after the date are skipped, so backfilling an old
     * day never blames someone who was not enrolled yet. Safe to run
     * repeatedly — it only fills gaps.
     *
     * @return int the number of pending records written
     */
    public function markPending(?CarbonInterface $date = null): int
    {
        $date ??= now();
        $day = $date->toDateString();
        $written = 0;

        $this->attendingStudents()
            ->where(fn (Builder $query) => $query
                ->whereNull('created_at')
                ->orWhere('created_at', '<=', $date->copy()->endOfDay()))
            ->whereDoesntHave('attendances', fn (Builder $query) => $query->whereDate('date', $day))
            ->select('id')
            ->chunkById(500, function ($students) use ($day, &$written): void {
                $now = now();

                $written += Attendance::query()->insertOrIgnore($students->map(fn (Student $student): array => [
                    'student_id' => $student->id,
                    'date' => $day,
                    'status' => AttendanceStatus::Pending->value,
                    'method' => 'system',
                    'note' => __('Tidak ada absensi tercatat — menunggu konfirmasi guru.'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        return $written;
    }

    /**
     * Students expected at school: everyone except those whose account has
     * been deactivated (moved or left). A student with no login yet still
     * attends through the kiosk, so a missing account does not exclude them.
     *
     * @return Builder<Student>
     */
    private function attendingStudents(): Builder
    {
        return Student::query()->where(fn (Builder $query) => $query
            ->whereNull('user_id')
            ->orWhereHas('user', fn (Builder $user) => $user->where('is_active', true)));
    }
}

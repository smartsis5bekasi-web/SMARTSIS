<?php

namespace App\Livewire\Concerns;

use App\Actions\Attendance\RecordAttendance;
use App\Actions\Attendance\StoreAttendanceSelfie;
use App\Exceptions\AttendanceException;
use App\Models\AttendanceSetting;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\AttendanceCapture;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The face-scan attendance flow shared by the staffed scan page and the
 * full-screen classroom kiosk: the browser (resources/js/face-attendance.js)
 * identifies the student and runs the blink check, then hands the matched id
 * to {@see self::record()}.
 */
trait HandlesFaceScan
{
    /** Either "masuk" (check-in, F-09) or "pulang" (check-out, F-10). */
    public string $mode = 'masuk';

    /**
     * The class the camera matches against; null means every class. Kept in
     * the URL so a classroom tablet can bookmark its own class.
     */
    #[Url(as: 'kelas', except: null)]
    public ?int $classroomId = null;

    /**
     * Outcome of the last scan, rendered as the result card.
     *
     * @var array{ok: bool, name: string, classroom: string|null, avatar: string|null, status: string, time: string, message: string}|null
     */
    public ?array $lastResult = null;

    /**
     * Pick the starting mode and drop a bookmarked class that no longer exists.
     */
    protected function bootFaceScan(): void
    {
        if ($this->setting()->isCheckOutOpen(now())) {
            $this->mode = 'pulang';
        }

        $this->normaliseClassroom();
    }

    #[Computed]
    public function setting(): AttendanceSetting
    {
        return AttendanceSetting::current();
    }

    /**
     * @return Collection<int, Classroom>
     */
    #[Computed]
    public function classrooms(): Collection
    {
        return Classroom::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function selectedClassroom(): ?Classroom
    {
        return $this->classroomId === null ? null : $this->classrooms->firstWhere('id', $this->classroomId);
    }

    /**
     * Where the browser fetches the templates for the current class filter.
     */
    public function templatesUrl(): string
    {
        return route('attendance.absensi.face-templates', array_filter(['classroom' => $this->classroomId]));
    }

    public function setMode(string $mode): void
    {
        abort_unless(in_array($mode, ['masuk', 'pulang'], true), 400);

        $this->mode = $mode;
        $this->lastResult = null;
    }

    public function updatedClassroomId(): void
    {
        $this->normaliseClassroom();
        $this->lastResult = null;
    }

    /**
     * Record the matched student's attendance after the blink challenge.
     * Check-out for a student who has not checked in is rejected by the
     * engine ({@see RecordAttendance::checkOut}) and surfaces as an error
     * card. The kiosk keeps the matched frame as evidence; the location is
     * the school itself, so no GPS fix is collected here.
     *
     * @param  array{photo?: string|null}  $capture
     */
    public function record(int $studentId, array $capture = []): void
    {
        $student = Student::query()->with('classroom')->findOrFail($studentId);

        // The browser only matches the selected class, so a student from
        // another class can only arrive here through a tampered request.
        if ($this->classroomId !== null && $student->classroom_id !== $this->classroomId) {
            $this->lastResult = $this->resultFor($student, false, __('Siswa ini bukan anggota kelas yang dipilih.'));

            return;
        }

        $engine = app(RecordAttendance::class);
        $checkingOut = $this->mode === 'pulang';

        $evidence = new AttendanceCapture(
            photoPath: app(StoreAttendanceSelfie::class)->handle(
                $capture['photo'] ?? null,
                $student,
                $checkingOut ? 'out' : 'in',
            ),
        );

        try {
            $attendance = $checkingOut
                ? $engine->checkOut($student, auth()->user(), $evidence)
                : $engine->checkIn($student, auth()->user(), 'face', $evidence);

            $this->lastResult = $this->resultFor(
                $student,
                true,
                $checkingOut
                    ? __('Absensi pulang tercatat.')
                    : __('Absensi masuk tercatat: :status.', ['status' => $attendance->status->label()]),
                $attendance->status->label(),
            );
        } catch (AttendanceException $exception) {
            $this->lastResult = $this->resultFor($student, false, $exception->getMessage());
        }
    }

    /**
     * @return array{ok: bool, name: string, classroom: string|null, avatar: string|null, status: string, time: string, message: string}
     */
    private function resultFor(Student $student, bool $ok, string $message, string $status = '—'): array
    {
        return [
            'ok' => $ok,
            'name' => $student->name,
            'classroom' => $student->classroom?->name,
            'avatar' => $student->avatar_url,
            'status' => $status,
            'time' => now()->format('H:i'),
            'message' => $message,
        ];
    }

    private function normaliseClassroom(): void
    {
        unset($this->selectedClassroom);

        if ($this->classroomId !== null && $this->selectedClassroom === null) {
            $this->classroomId = null;
        }
    }
}

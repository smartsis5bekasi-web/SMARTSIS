<?php

namespace App\Livewire\Concerns;

use App\Actions\Student\RegisterStudentFace;
use App\Enums\FaceConflictResolution;
use App\Exceptions\DuplicateFaceException;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Staff-side face registration (the kiosk registration mode and the admin
 * "Daftarkan Wajah" page): a capture that lands close to another student's
 * face is held back and shown next to that student's photo, so whoever is
 * standing with the student can see which of the two records is wrong
 * instead of reading a name in a dead-end error.
 *
 * Depending on {@see FaceConflictResolution} they can then move a
 * self-registered face over, keep two look-alikes, or only retake the photo.
 */
trait ResolvesFaceConflicts
{
    /** Minutes a held-back capture waits for the staff member's decision. */
    private const PENDING_CAPTURE_MINUTES = 10;

    /**
     * The other student the held-back capture ran into.
     *
     * @var array{owner_id: int, name: string, classroom: string|null, avatar: string|null, source: string, distance: float, resolution: string|null}|null
     */
    #[Locked]
    public ?array $faceConflict = null;

    /** Cache key of the held-back capture; the descriptors never round-trip through the browser again. */
    #[Locked]
    public ?string $pendingCaptureKey = null;

    /**
     * The student the capture on screen is being registered for.
     */
    abstract protected function faceCaptureStudent(): ?Student;

    /**
     * Store a capture for {@see self::faceCaptureStudent()} and report the
     * outcome on screen.
     *
     * @param  array<int, array<int, float|int>>  $descriptors
     */
    abstract protected function registerFace(array $descriptors, ?string $snapshot, ?int $confirmedOwnerId = null): void;

    /**
     * The held-back frame, shown beside the other student's photo.
     */
    #[Computed]
    public function pendingSnapshot(): ?string
    {
        $snapshot = $this->pendingCapture()['snapshot'] ?? null;

        return is_string($snapshot) && str_starts_with($snapshot, 'data:image/jpeg;base64,') ? $snapshot : null;
    }

    /**
     * Go ahead with the held-back capture the way the dialog offered.
     */
    public function confirmFaceConflict(): void
    {
        $capture = $this->pendingCapture();
        $ownerId = $this->faceConflict['owner_id'] ?? null;
        $resolvable = ($this->faceConflict['resolution'] ?? null) !== null;
        $student = $this->faceCaptureStudent();

        $this->forgetPendingCapture();

        if ($capture === null || $ownerId === null || ! $resolvable || $student === null || $student->id !== $capture['student_id']) {
            $this->dispatch('face-capture-rejected');
            $this->dispatch('swal', icon: 'warning', title: __('Foto sudah kedaluwarsa. Silakan ambil ulang.'));

            return;
        }

        $this->registerFace($capture['descriptors'], $capture['snapshot'], $ownerId);
    }

    /**
     * Drop the held-back capture and bring the camera back for a retake.
     */
    public function dismissFaceConflict(): void
    {
        $this->forgetPendingCapture();
        $this->dispatch('face-capture-rejected');
    }

    /**
     * Run the registration, holding the capture back for the comparison
     * dialog when it looks like another student.
     *
     * @param  array<int, array<int, float|int>>  $descriptors
     * @return bool whether the face was stored
     */
    protected function attemptFaceRegistration(Student $student, User $operator, array $descriptors, ?string $snapshot, ?int $confirmedOwnerId = null): bool
    {
        try {
            $stored = app(RegisterStudentFace::class)->handle($student, $descriptors, $snapshot, $operator, $confirmedOwnerId);
        } catch (DuplicateFaceException $exception) {
            $this->holdConflictingCapture($student, $exception, $descriptors, $snapshot);

            return false;
        }

        if (! $stored) {
            $this->dispatch('face-capture-rejected');
            $this->dispatch('swal', icon: 'error', title: __('Data wajah tidak valid. Silakan ulangi.'));
        }

        return $stored;
    }

    protected function forgetPendingCapture(): void
    {
        if ($this->pendingCaptureKey !== null) {
            Cache::forget($this->pendingCaptureKey);
        }

        $this->pendingCaptureKey = null;
        $this->faceConflict = null;
        unset($this->pendingSnapshot);
    }

    /**
     * @param  array<int, array<int, float|int>>  $descriptors
     */
    private function holdConflictingCapture(Student $student, DuplicateFaceException $exception, array $descriptors, ?string $snapshot): void
    {
        $this->forgetPendingCapture();

        $owner = $exception->owner->loadMissing('faceRegisteredBy:id,name');

        $this->pendingCaptureKey = 'face-capture:'.Str::uuid();

        Cache::put($this->pendingCaptureKey, [
            'student_id' => $student->id,
            'descriptors' => $descriptors,
            'snapshot' => $snapshot,
        ], now()->addMinutes(self::PENDING_CAPTURE_MINUTES));

        $this->faceConflict = [
            'owner_id' => $owner->id,
            'name' => $owner->name,
            'classroom' => $owner->classroom?->name,
            'avatar' => $owner->avatar_url,
            'source' => $owner->hasStaffVerifiedFace()
                ? __('diverifikasi :name', ['name' => $owner->faceRegisteredBy?->name ?? __('petugas')])
                : __('didaftarkan mandiri'),
            'distance' => round($exception->distance, 2),
            'resolution' => $exception->resolution?->value,
        ];
    }

    /**
     * @return array{student_id: int, descriptors: array<int, array<int, float|int>>, snapshot: string|null}|null
     */
    private function pendingCapture(): ?array
    {
        if ($this->pendingCaptureKey === null) {
            return null;
        }

        $capture = Cache::get($this->pendingCaptureKey);

        return is_array($capture) ? $capture : null;
    }
}

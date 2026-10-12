<?php

namespace App\Actions\Student;

use App\Exceptions\DuplicateFaceException;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Store the face template the browser captured with face-api, plus the frame
 * it was taken from as the student's profile photo.
 *
 * Shared by the student's own onboarding, the admin "Daftarkan Wajah" page and
 * the kiosk registration mode, so every path accepts exactly the same payload
 * and runs the same duplicate check.
 */
class RegisterStudentFace
{
    /** Samples per template: the wizard sends one, older clients sent three. */
    private const MAX_SAMPLES = 3;

    /** face-api FaceRecognitionNet descriptors are 128-dimensional. */
    private const DIMENSIONS = 128;

    /** Snapshots are downscaled client-side; anything larger is a tampered payload. */
    private const MAX_SNAPSHOT_BYTES = 2 * 1024 * 1024;

    /**
     * Same cut-off the kiosk matcher uses (resources/js/face-attendance.js):
     * two templates closer than this are, as far as the kiosk is concerned,
     * the same person.
     */
    private const DUPLICATE_DISTANCE = 0.5;

    /**
     * @param  array<int, mixed>  $descriptors  one to three samples of 128 floats
     * @param  string|null  $snapshot  a `data:image/jpeg;base64,…` string
     * @param  User|null  $registeredBy  who captured it; staff other than the student marks the face as verified in person
     * @return bool false when the descriptors are malformed and nothing was stored
     *
     * @throws DuplicateFaceException when the face is already enrolled on another student
     */
    public function handle(Student $student, array $descriptors, ?string $snapshot = null, ?User $registeredBy = null): bool
    {
        if (! $this->isValid($descriptors)) {
            return false;
        }

        $owner = $this->findLookAlike($descriptors, $student);

        if ($owner !== null) {
            throw new DuplicateFaceException($owner);
        }

        $attributes = [
            'face_descriptors' => $descriptors,
            'face_registered_at' => now(),
            'face_registered_by' => $registeredBy?->id,
            ...$this->storeSnapshotAsAvatar($student, $snapshot),
        ];

        // Staff saw the student in person, which is a stronger identity check
        // than the NISN the onboarding asks for, so there is nothing left for
        // the student to do on first login.
        if ($registeredBy !== null && $registeredBy->id !== $student->user_id) {
            $attributes['nisn_verified_at'] = $student->nisn_verified_at ?? now();
            $attributes['onboarded_at'] = $student->onboarded_at ?? now();
        }

        $student->update($attributes);

        return true;
    }

    /**
     * @param  array<int, mixed>  $descriptors
     */
    private function isValid(array $descriptors): bool
    {
        return count($descriptors) >= 1 && count($descriptors) <= self::MAX_SAMPLES && collect($descriptors)->every(
            fn ($sample): bool => is_array($sample)
                && count($sample) === self::DIMENSIONS
                && collect($sample)->every(fn ($value): bool => is_numeric($value)),
        );
    }

    /**
     * The other student whose stored template is closest to the capture, when
     * it is close enough to be the same face.
     *
     * @param  array<int, array<int, float|int>>  $descriptors
     */
    private function findLookAlike(array $descriptors, Student $student): ?Student
    {
        $closest = null;
        $closestDistance = self::DUPLICATE_DISTANCE;

        Student::query()
            ->whereNotNull('face_descriptors')
            ->whereKeyNot($student->getKey())
            ->select(['id', 'name', 'classroom_id', 'face_descriptors'])
            ->lazyById(200)
            ->each(function (Student $candidate) use ($descriptors, &$closest, &$closestDistance): void {
                foreach ($candidate->face_descriptors ?? [] as $stored) {
                    foreach ($descriptors as $captured) {
                        $distance = $this->distance($captured, $stored);

                        if ($distance < $closestDistance) {
                            $closest = $candidate;
                            $closestDistance = $distance;
                        }
                    }
                }
            });

        return $closest?->load('classroom:id,name');
    }

    /**
     * Euclidean distance between two descriptors; malformed stored data never
     * counts as a match.
     *
     * @param  array<int, float|int>  $a
     */
    private function distance(array $a, mixed $b): float
    {
        if (! is_array($b) || count($b) !== count($a)) {
            return INF;
        }

        $sum = 0.0;

        foreach ($a as $index => $value) {
            $delta = (float) $value - (float) ($b[$index] ?? 0);
            $sum += $delta * $delta;
        }

        return sqrt($sum);
    }

    /**
     * Save the captured face snapshot as the student's profile photo. The
     * snapshot is optional; the face template is stored either way.
     *
     * @return array{avatar_url?: string}
     */
    private function storeSnapshotAsAvatar(Student $student, ?string $snapshot): array
    {
        if ($snapshot === null || ! str_starts_with($snapshot, 'data:image/jpeg;base64,')) {
            return [];
        }

        $binary = base64_decode(substr($snapshot, strlen('data:image/jpeg;base64,')), true);

        // Reject anything that is not a real, reasonably sized image.
        if ($binary === false || strlen($binary) > self::MAX_SNAPSHOT_BYTES || @getimagesizefromstring($binary) === false) {
            return [];
        }

        $oldPath = $student->avatar_url !== null
            ? str_replace(Storage::url(''), '', $student->avatar_url)
            : null;

        $path = 'students/face-'.$student->id.'-'.now()->timestamp.'.jpg';
        Storage::disk('public')->put($path, $binary);

        if ($oldPath !== null && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return ['avatar_url' => Storage::url($path)];
    }
}

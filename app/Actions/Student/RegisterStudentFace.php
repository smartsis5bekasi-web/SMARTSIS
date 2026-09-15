<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;

/**
 * Store the face template the browser captured with face-api, plus the frame
 * it was taken from as the student's profile photo.
 *
 * Shared by the student's own onboarding and the admin "Daftarkan Wajah" page,
 * so both paths accept exactly the same payload.
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
     * @param  array<int, mixed>  $descriptors  one to three samples of 128 floats
     * @param  string|null  $snapshot  a `data:image/jpeg;base64,…` string
     * @return bool false when the descriptors are malformed and nothing was stored
     */
    public function handle(Student $student, array $descriptors, ?string $snapshot = null): bool
    {
        if (! $this->isValid($descriptors)) {
            return false;
        }

        $student->update([
            'face_descriptors' => $descriptors,
            'face_registered_at' => now(),
            ...$this->storeSnapshotAsAvatar($student, $snapshot),
        ]);

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

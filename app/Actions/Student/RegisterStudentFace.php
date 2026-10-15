<?php

namespace App\Actions\Student;

use App\Enums\FaceConflictResolution;
use App\Exceptions\DuplicateFaceException;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Below this the two templates are, for all practical purposes, the same
     * face. Between this and {@see self::DUPLICATE_DISTANCE} two different
     * students can genuinely land — siblings, or two look-alikes caught by the
     * same tablet under the same light — so staff may keep both after
     * comparing the photos.
     */
    private const SAME_PERSON_DISTANCE = 0.4;

    /**
     * @param  array<int, mixed>  $descriptors  one to three samples of 128 floats
     * @param  string|null  $snapshot  a `data:image/jpeg;base64,…` string
     * @param  User|null  $registeredBy  who captured it; staff other than the student marks the face as verified in person
     * @param  int|null  $confirmedOwnerId  the look-alike student staff already compared photos with and chose to resolve
     * @return bool false when the descriptors are malformed and nothing was stored
     *
     * @throws DuplicateFaceException when the face is already enrolled on another student and that was not confirmed
     */
    public function handle(Student $student, array $descriptors, ?string $snapshot = null, ?User $registeredBy = null, ?int $confirmedOwnerId = null): bool
    {
        if (! $this->isValid($descriptors)) {
            return false;
        }

        $isStaffCapture = $registeredBy !== null && $registeredBy->id !== $student->user_id;
        $lookAlikes = $this->findLookAlikes($descriptors, $student);
        $resolution = null;

        if ($lookAlikes !== []) {
            ['student' => $owner, 'distance' => $distance] = $lookAlikes[0];

            // A second look-alike means the capture itself is suspect (a
            // blurred or badly lit frame sits close to everyone), so it is
            // never resolvable.
            $resolution = $isStaffCapture && count($lookAlikes) === 1
                ? $this->resolutionFor($owner, $distance)
                : null;

            if ($resolution === null || $owner->id !== $confirmedOwnerId) {
                Log::info('Face registration matched another student.', [
                    'student_id' => $student->id,
                    'owner_id' => $owner->id,
                    'distance' => round($distance, 4),
                    'look_alikes' => count($lookAlikes),
                    'registered_by' => $registeredBy?->id,
                    'resolution' => $resolution?->value,
                ]);

                throw new DuplicateFaceException($owner->load('classroom:id,name'), $distance, $resolution);
            }
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
        if ($isStaffCapture) {
            $attributes['nisn_verified_at'] = $student->nisn_verified_at ?? now();
            $attributes['onboarded_at'] = $student->onboarded_at ?? now();
        }

        DB::transaction(function () use ($student, $attributes, $resolution, $lookAlikes, $registeredBy): void {
            if ($resolution === FaceConflictResolution::Transfer) {
                $owner = $lookAlikes[0]['student'];

                $owner->update([
                    'face_descriptors' => null,
                    'face_registered_at' => null,
                    'face_registered_by' => null,
                ]);

                Log::info('Self-registered face moved to the student verified in person.', [
                    'from_student_id' => $owner->id,
                    'to_student_id' => $student->id,
                    'registered_by' => $registeredBy->id,
                ]);
            }

            $student->update($attributes);
        });

        return true;
    }

    /**
     * How staff may go ahead despite the look-alike, or null when they may not.
     */
    private function resolutionFor(Student $owner, float $distance): ?FaceConflictResolution
    {
        if (! $owner->hasStaffVerifiedFace()) {
            return FaceConflictResolution::Transfer;
        }

        return $distance >= self::SAME_PERSON_DISTANCE ? FaceConflictResolution::KeepBoth : null;
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
     * Every other student with a stored template close enough to be the same
     * face, closest first.
     *
     * @param  array<int, array<int, float|int>>  $descriptors
     * @return list<array{student: Student, distance: float}>
     */
    private function findLookAlikes(array $descriptors, Student $student): array
    {
        $lookAlikes = [];

        Student::query()
            ->whereNotNull('face_descriptors')
            ->whereKeyNot($student->getKey())
            ->select(['id', 'name', 'classroom_id', 'user_id', 'avatar_url', 'face_descriptors', 'face_registered_at', 'face_registered_by'])
            ->lazyById(200)
            ->each(function (Student $candidate) use ($descriptors, &$lookAlikes): void {
                $closest = INF;

                foreach ($candidate->face_descriptors ?? [] as $stored) {
                    foreach ($descriptors as $captured) {
                        $closest = min($closest, $this->distance($captured, $stored));
                    }
                }

                if ($closest < self::DUPLICATE_DISTANCE) {
                    $lookAlikes[] = ['student' => $candidate, 'distance' => $closest];
                }
            });

        usort($lookAlikes, fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);

        return $lookAlikes;
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

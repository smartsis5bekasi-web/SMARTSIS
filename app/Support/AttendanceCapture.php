<?php

namespace App\Support;

/**
 * Everything the browser collects at the moment a student presses "absen":
 * the selfie frame (already stored, as a public URL) and the GPS fix.
 *
 * All of it is optional — a denied camera or location permission must never
 * block the attendance itself, it only leaves the evidence columns empty.
 */
final readonly class AttendanceCapture
{
    public function __construct(
        public ?string $photoPath = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $accuracy = null,
    ) {}

    /**
     * Build a capture from a raw browser payload, keeping only coordinates
     * that are inside the valid WGS84 range.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, ?string $photoPath = null): self
    {
        $latitude = is_numeric($payload['latitude'] ?? null) ? (float) $payload['latitude'] : null;
        $longitude = is_numeric($payload['longitude'] ?? null) ? (float) $payload['longitude'] : null;

        $hasFix = $latitude !== null
            && $longitude !== null
            && abs($latitude) <= 90
            && abs($longitude) <= 180;

        return new self(
            photoPath: $photoPath,
            latitude: $hasFix ? $latitude : null,
            longitude: $hasFix ? $longitude : null,
            accuracy: $hasFix && is_numeric($payload['accuracy'] ?? null)
                ? (int) round(min(max((float) $payload['accuracy'], 0), 100000))
                : null,
        );
    }

    /**
     * Whether a usable GPS fix came back with this capture.
     *
     * This is what verifies a self-service absensi: the face photo is
     * evidence, but the location is the check that the student was actually
     * somewhere plausible when they pressed the button.
     */
    public function hasFix(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The columns to write for a check-in.
     *
     * @return array<string, mixed>
     */
    public function checkInAttributes(): array
    {
        return [
            'check_in_photo_path' => $this->photoPath,
            'check_in_latitude' => $this->latitude,
            'check_in_longitude' => $this->longitude,
            'check_in_accuracy' => $this->accuracy,
        ];
    }

    /**
     * The columns to write for a check-out.
     *
     * @return array<string, mixed>
     */
    public function checkOutAttributes(): array
    {
        return [
            'check_out_photo_path' => $this->photoPath,
            'check_out_latitude' => $this->latitude,
            'check_out_longitude' => $this->longitude,
            'check_out_accuracy' => $this->accuracy,
        ];
    }
}

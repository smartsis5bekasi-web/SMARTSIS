<?php

namespace App\Actions\Attendance;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Persist the base64 frame the absensi camera grabbed and return its public
 * URL. The payload comes straight from the browser, so it is validated as a
 * real, bounded image before anything touches disk.
 */
class StoreAttendanceSelfie
{
    /** JPEG frames are resized client-side; anything larger is a tampered payload. */
    private const MAX_BYTES = 2 * 1024 * 1024;

    /** @var array<string, string> */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param  string|null  $dataUrl  a `data:image/jpeg;base64,…` string
     * @return string|null the public URL, or null when there is nothing usable
     */
    public function handle(?string $dataUrl, Student $student, string $moment): ?string
    {
        if (! is_string($dataUrl) || $dataUrl === '') {
            return null;
        }

        if (! preg_match('#^data:(image/(?:jpeg|png|webp));base64,(.+)$#s', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        // Trust the bytes, not the declared mime: getimagesizefromstring
        // rejects anything that is not a decodable image.
        $size = @getimagesizefromstring($binary);

        if ($size === false || ! isset(self::ALLOWED_MIMES[$size['mime']])) {
            return null;
        }

        $path = sprintf(
            'attendances/%s/%d-%s-%s.%s',
            now()->format('Y/m'),
            $student->id,
            $moment,
            Str::random(12),
            self::ALLOWED_MIMES[$size['mime']],
        );

        Storage::disk('public')->put($path, $binary);

        return Storage::url($path);
    }
}

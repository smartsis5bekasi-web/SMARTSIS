<?php

namespace App\Exceptions;

use App\Enums\FaceConflictResolution;
use App\Models\Student;
use RuntimeException;

/**
 * The captured face is already enrolled on another student. Storing it would
 * let one person check in as two students, and the kiosk could not tell the
 * two records apart anyway.
 *
 * The message names the other student, so it is only for staff screens; a
 * student registering their own face is shown {@see self::forStudent()}.
 *
 * {@see self::$resolutions} are the ways staff may still go ahead after
 * comparing the two photos, the suggested one ({@see self::$resolution})
 * first; empty when the capture has to be refused outright.
 */
class DuplicateFaceException extends RuntimeException
{
    public readonly ?FaceConflictResolution $resolution;

    /**
     * @param  list<FaceConflictResolution>  $resolutions
     */
    public function __construct(
        public readonly Student $owner,
        public readonly float $distance = 0.0,
        public readonly array $resolutions = [],
    ) {
        $this->resolution = $resolutions[0] ?? null;

        parent::__construct(__('Wajah ini sudah terdaftar atas nama :name (:classroom). Pastikan siswa yang difoto benar, atau perbarui data wajah :name terlebih dahulu.', [
            'name' => $owner->name,
            'classroom' => $owner->classroom->name ?? __('tanpa kelas'),
        ]));
    }

    /**
     * The same refusal without revealing whose account the face is on.
     */
    public function forStudent(): string
    {
        return __('Wajah ini sudah terdaftar pada akun siswa lain. Hubungi wali kelas atau admin sekolah.');
    }
}

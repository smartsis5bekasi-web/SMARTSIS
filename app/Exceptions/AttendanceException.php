<?php

namespace App\Exceptions;

use App\Models\Attendance;
use RuntimeException;

/**
 * Domain errors raised while recording attendance. The message is
 * user-facing (shown on the kiosk / monitoring page as-is).
 */
class AttendanceException extends RuntimeException
{
    public static function alreadyRecorded(Attendance $attendance): self
    {
        return new self(__('Absensi hari ini sudah tercatat dengan status :status.', [
            'status' => $attendance->status->label(),
        ]));
    }

    public static function notCheckedIn(): self
    {
        return new self(__('Belum ada absensi masuk hari ini. Lakukan absensi masuk terlebih dahulu.'));
    }

    public static function alreadyCheckedOut(): self
    {
        return new self(__('Absensi pulang hari ini sudah tercatat.'));
    }

    public static function checkOutNotOpen(string $opensAt): self
    {
        return new self(__('Absensi pulang baru dibuka pukul :time.', ['time' => $opensAt]));
    }
}

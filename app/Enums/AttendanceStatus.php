<?php

namespace App\Enums;

/**
 * Daily attendance statuses monitored by the Smart Attendance module
 * (PRD F-11 — hadir, terlambat, izin, sakit, alpha).
 */
enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpha = 'alpha';

    /**
     * The human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpha => 'Alpha',
        };
    }

    /**
     * Whether the student physically attended school (eligible for check-out).
     */
    public function isPresent(): bool
    {
        return in_array($this, [self::Hadir, self::Terlambat], true);
    }

    /**
     * All status backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}

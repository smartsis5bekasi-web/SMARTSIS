<?php

namespace App\Enums;

/**
 * The activity that triggers a point change ("Cara Mendapatkan / Mengurangi
 * Point"). Binds a point rule to the module that drives it so the Dynamic
 * Point Engine can apply the rule automatically when that module approves an
 * event (PRD section 6.1 — Event → Listener).
 */
enum PointSource: string
{
    case Attendance = 'attendance';
    case Achievement = 'achievement';
    case Violation = 'violation';
    case Manual = 'manual';

    /**
     * The human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Absensi',
            self::Achievement => 'Prestasi',
            self::Violation => 'Pelanggaran',
            self::Manual => 'Manual',
        };
    }

    /**
     * The point types this source may produce. Prestasi only adds points,
     * Pelanggaran only deducts; Absensi and Manual can do either (e.g. hadir
     * tepat waktu vs. terlambat/alpha).
     *
     * @return array<int, PointType>
     */
    public function allowedTypes(): array
    {
        return match ($this) {
            self::Achievement => [PointType::Addition],
            self::Violation => [PointType::Deduction],
            self::Attendance, self::Manual => [PointType::Addition, PointType::Deduction],
        };
    }

    /**
     * All source backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $source): string => $source->value, self::cases());
    }
}

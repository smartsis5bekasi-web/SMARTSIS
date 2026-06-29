<?php

namespace App\Enums;

/**
 * The verification lifecycle shared by violations and achievements. Points are
 * only applied once a record reaches {@see self::Approved} (PRD F-16 / F-19 —
 * approval performed by Guru BK).
 */
enum PointApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * The human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * Tailwind/Flux badge colour used to highlight the status in the UI.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
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

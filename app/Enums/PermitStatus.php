<?php

namespace App\Enums;

/**
 * The Guru Piket decision on a permit (PRD 7.8 — Menunggu Persetujuan /
 * Disetujui / Ditolak). The student's Wali Kelas must approve as well; that
 * sign-off lives beside the status, so show users `Permit::statusLabel()`.
 */
enum PermitStatus: string
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
            self::Pending => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
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

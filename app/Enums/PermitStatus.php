<?php

namespace App\Enums;

/**
 * The permit approval lifecycle (PRD 7.8 — Menunggu Persetujuan /
 * Disetujui / Ditolak). Approval is performed by Guru Piket or the
 * student's Wali Kelas (F-25).
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

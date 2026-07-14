<?php

namespace App\Enums;

/**
 * The warning letter lifecycle: the system generates a recommendation
 * (Pending, F-21), then Guru BK approves — which issues the letter — or
 * rejects it (F-22).
 */
enum WarningStatus: string
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
            self::Pending => 'Rekomendasi',
            self::Approved => 'Diterbitkan',
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

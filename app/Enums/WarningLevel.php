<?php

namespace App\Enums;

/**
 * Warning letter severity levels (PRD F-20 — SP1, SP2, SP3). Higher value
 * means a more severe letter, triggered by a lower point threshold.
 */
enum WarningLevel: int
{
    case Sp1 = 1;
    case Sp2 = 2;
    case Sp3 = 3;

    /**
     * The short label used in tables and badges.
     */
    public function label(): string
    {
        return 'SP'.$this->value;
    }

    /**
     * The formal name used on the printed letter.
     */
    public function letterTitle(): string
    {
        return 'Surat Peringatan '.$this->value;
    }

    /**
     * All level backed values.
     *
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(fn (self $level): int => $level->value, self::cases());
    }
}

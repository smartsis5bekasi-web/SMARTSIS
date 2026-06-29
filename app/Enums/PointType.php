<?php

namespace App\Enums;

/**
 * Whether a point rule adds to or subtracts from a student's discipline point
 * balance (PRD section 6.1 / KAK Modul D — Dynamic Point System).
 */
enum PointType: string
{
    case Addition = 'addition';
    case Deduction = 'deduction';

    /**
     * The human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Addition => 'Penambahan',
            self::Deduction => 'Pengurangan',
        };
    }

    /**
     * The sign applied to a rule's magnitude when computing the point delta.
     */
    public function sign(): int
    {
        return $this === self::Addition ? 1 : -1;
    }

    /**
     * Tailwind/Flux badge colour used to highlight the type in the UI.
     */
    public function badgeColor(): string
    {
        return $this === self::Addition ? 'green' : 'red';
    }

    /**
     * All type backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}

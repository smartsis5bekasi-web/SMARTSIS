<?php

namespace App\Enums;

/**
 * The follow-ups Guru BK is recommended to take for a student, and the kind
 * of pemanggilan recorded once BK has acted on one:
 *
 * - Konseling: the student has {@see self::ALPHA_THRESHOLD} or more Alpha days.
 * - Pembinaan: the discipline point is below {@see self::GUIDANCE_POINT_BELOW}.
 * - Pemanggilan Ortu: the point is below {@see self::PARENT_CALL_POINT_BELOW}
 *   (replaces Pembinaan at that level).
 */
enum CounselingAction: string
{
    case Konseling = 'konseling';
    case Pembinaan = 'pembinaan';
    case PemanggilanOrtu = 'sp_ortu';

    public const ALPHA_THRESHOLD = 3;

    public const GUIDANCE_POINT_BELOW = 70;

    public const PARENT_CALL_POINT_BELOW = 50;

    /**
     * The human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Konseling => 'Konseling',
            self::Pembinaan => 'Pembinaan',
            self::PemanggilanOrtu => 'Pemanggilan Ortu',
        };
    }

    /**
     * Tailwind classes for the recommendation pill.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Konseling => 'bg-blue-100 text-blue-700 border-blue-200',
            self::Pembinaan => 'bg-amber-100 text-amber-700 border-amber-200',
            self::PemanggilanOrtu => 'bg-red-100 text-red-700 border-red-200',
        };
    }

    /**
     * The actions the rules recommend for a student with the given Alpha
     * count and discipline point.
     *
     * @return array<int, self>
     */
    public static function recommendedFor(int $alphaCount, int $point): array
    {
        $actions = [];

        if ($alphaCount >= self::ALPHA_THRESHOLD) {
            $actions[] = self::Konseling;
        }

        if ($point < self::PARENT_CALL_POINT_BELOW) {
            $actions[] = self::PemanggilanOrtu;
        } elseif ($point < self::GUIDANCE_POINT_BELOW) {
            $actions[] = self::Pembinaan;
        }

        return $actions;
    }

    /**
     * All action backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $action): string => $action->value, self::cases());
    }
}

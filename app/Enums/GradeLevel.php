<?php

namespace App\Enums;

/**
 * The tingkat of a classroom (kelas X, XI, XII). Lets a holiday be declared
 * for whole grades at once — "kelas XII masuk, kelas X dan XI libur" — instead
 * of picking every classroom by hand.
 */
enum GradeLevel: int
{
    case Ten = 10;
    case Eleven = 11;
    case Twelve = 12;

    /**
     * The human-readable Indonesian label.
     */
    public function label(): string
    {
        return 'Kelas '.$this->numeral();
    }

    /**
     * The Roman numeral classroom names start with ("XI IPA 1").
     */
    public function numeral(): string
    {
        return match ($this) {
            self::Ten => 'X',
            self::Eleven => 'XI',
            self::Twelve => 'XII',
        };
    }

    /**
     * All grade backed values.
     *
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(fn (self $grade): int => $grade->value, self::cases());
    }

    /**
     * Guess the grade from a classroom name such as "XI IPA 1" or "10-2", for
     * classrooms created before the grade was recorded.
     */
    public static function guessFromName(string $name): ?self
    {
        $prefix = strtoupper(trim($name));

        return match (true) {
            (bool) preg_match('/^(XII|12)(?![0-9A-Z])/', $prefix) => self::Twelve,
            (bool) preg_match('/^(XI|11)(?![0-9A-Z])/', $prefix) => self::Eleven,
            (bool) preg_match('/^(X|10)(?![0-9A-Z])/', $prefix) => self::Ten,
            default => null,
        };
    }
}

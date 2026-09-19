<?php

namespace App\Enums;

/**
 * The permit kinds a student may request (PRD F-24 — izin terlambat,
 * izin keluar, izin pulang awal).
 */
enum PermitType: string
{
    case Terlambat = 'terlambat';
    case Keluar = 'keluar';
    case PulangAwal = 'pulang_awal';

    /**
     * The human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Terlambat => 'Izin Terlambat',
            self::Keluar => 'Izin Keluar',
            self::PulangAwal => 'Izin Pulang Awal',
        };
    }

    /**
     * Short helper text shown next to the option on the request form.
     */
    public function description(): string
    {
        return match ($this) {
            self::Terlambat => 'Datang terlambat tanpa pengurangan poin pada tanggal tersebut.',
            self::Keluar => 'Meninggalkan sekolah sementara pada jam pelajaran.',
            self::PulangAwal => 'Absensi pulang lebih awal dari jam pulang.',
        };
    }

    /**
     * The types that may be picked in the UI right now. Izin Terlambat is
     * temporarily withdrawn at the client's request; the case itself stays so
     * existing permits and the late-penalty waiver keep working.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type !== self::Terlambat,
        ));
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

    /**
     * Backed values of the currently selectable types.
     *
     * @return array<int, string>
     */
    public static function selectableValues(): array
    {
        return array_map(fn (self $type): string => $type->value, self::selectable());
    }
}

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
     * All type backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}

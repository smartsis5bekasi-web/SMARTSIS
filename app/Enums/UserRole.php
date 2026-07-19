<?php

namespace App\Enums;

/**
 * The nine RBAC roles defined by the SMARTSIS PRD.
 *
 * The backed values are used as the Spatie role names stored in the database,
 * while {@see self::label()} provides the human-readable Indonesian label.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case KepalaSekolah = 'kepala_sekolah';
    case WakasekKesiswaan = 'wakasek_kesiswaan';
    case GuruBk = 'guru_bk';
    case WaliKelas = 'wali_kelas';
    case GuruPiket = 'guru_piket';
    case GuruMapel = 'guru_mapel';
    case Siswa = 'siswa';
    case OrangTua = 'orang_tua';

    /**
     * The human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::KepalaSekolah => 'Kepala Sekolah',
            self::WakasekKesiswaan => 'Wakasek Kesiswaan',
            self::GuruBk => 'Guru BK',
            self::WaliKelas => 'Wali Kelas',
            self::GuruPiket => 'Guru Piket',
            self::GuruMapel => 'Guru Mata Pelajaran',
            self::Siswa => 'Siswa',
            self::OrangTua => 'Orang Tua/Wali',
        };
    }

    /**
     * All role backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Roles a teacher account may hold.
     *
     * @return array<int, self>
     */
    public static function teacherRoles(): array
    {
        return [
            self::KepalaSekolah,
            self::WakasekKesiswaan,
            self::GuruBk,
            self::WaliKelas,
            self::GuruPiket,
            self::GuruMapel,
        ];
    }
}

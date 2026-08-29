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
     * A short description of who holds the role, shown on "Manajemen Peran".
     */
    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Administrator sistem, memiliki seluruh akses tanpa terkecuali.',
            self::KepalaSekolah => 'Pimpinan sekolah, memantau seluruh laporan kesiswaan.',
            self::WakasekKesiswaan => 'Penanggung jawab bidang kesiswaan.',
            self::GuruBk => 'Bimbingan konseling, menangani pelanggaran dan pembinaan siswa.',
            self::WaliKelas => 'Pembina kelas, memantau siswa di kelas binaannya.',
            self::GuruPiket => 'Petugas piket harian, mengelola absensi dan perizinan.',
            self::GuruMapel => 'Guru pengajar mata pelajaran.',
            self::Siswa => 'Peserta didik, mengajukan izin dan prestasi.',
            self::OrangTua => 'Orang tua/wali, memantau perkembangan anaknya.',
        };
    }

    /**
     * Whether the role's permissions are fixed and may not be edited.
     *
     * Super Admin bypasses every gate through the Gate::before rule in
     * AppServiceProvider, so editing its permission list would be misleading.
     */
    public function isLocked(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * The permissions the role ships with, per the PRD access matrix
     * (PRD section 3.1 / KAK section 5.4).
     *
     * These are the defaults applied by RolePermissionSeeder and restored by
     * the "Kembalikan ke Bawaan" action on the role editor. The permissions a
     * role actually holds live in the database and may differ once an admin
     * has customised them.
     *
     * @return array<int, string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::values(),

            self::KepalaSekolah, self::WakasekKesiswaan => self::permissionValues([
                Permission::ViewDashboard,
                Permission::ViewAttendance,
                Permission::ViewViolation,
                Permission::ViewPoint,
                Permission::ViewAchievement,
                Permission::ViewPermit,
                Permission::ViewWarning,
            ]),

            self::GuruBk => self::permissionValues([
                Permission::ViewDashboard,
                Permission::ViewAttendance,
                Permission::ViewViolation,
                Permission::ManageViolation,
                Permission::ViewPoint,
                Permission::ManagePoint,
                Permission::ViewAchievement,
                Permission::ManageAchievement,
                Permission::ViewPermit,
                Permission::ViewWarning,
                Permission::ManageWarning,
            ]),

            self::WaliKelas => self::permissionValues([
                Permission::ViewDashboard,
                Permission::ViewAttendance,
                Permission::ViewViolation,
                Permission::ViewPoint,
                Permission::ViewAchievement,
                Permission::ViewPermit,
                Permission::ViewWarning,
            ]),

            self::GuruPiket => self::permissionValues([
                Permission::ViewDashboard,
                Permission::ViewAttendance,
                Permission::ManageAttendance,
                Permission::ViewViolation,
                Permission::InputViolation,
                Permission::ViewPoint,
                Permission::ViewPermit,
                Permission::ManagePermit,
            ]),

            self::GuruMapel => self::permissionValues([
                Permission::ViewDashboard,
                Permission::ViewAttendance,
                Permission::ViewViolation,
            ]),

            self::Siswa => self::permissionValues([
                Permission::ViewDashboard,
                Permission::ViewAttendance,
                Permission::ViewViolation,
                Permission::ViewPoint,
                Permission::ViewAchievement,
                Permission::RequestAchievement,
                Permission::ViewPermit,
                Permission::RequestPermit,
                Permission::ViewWarning,
            ]),

            self::OrangTua => self::permissionValues([
                Permission::ViewDashboard,
                Permission::ViewAttendance,
                Permission::ViewViolation,
                Permission::ViewPoint,
                Permission::ViewAchievement,
                Permission::ViewPermit,
                Permission::ViewWarning,
            ]),
        };
    }

    /**
     * @param  array<int, Permission>  $permissions
     * @return array<int, string>
     */
    private static function permissionValues(array $permissions): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, $permissions);
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

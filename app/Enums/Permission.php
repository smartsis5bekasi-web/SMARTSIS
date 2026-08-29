<?php

namespace App\Enums;

/**
 * Granular permissions derived from the PRD access matrix (section 3.1 / 5.4).
 *
 * Naming convention: "{module}.{action}". "manage" implies full CRUD,
 * "view" is read-only, and the remaining actions are module-specific.
 *
 * The cases here are the catalogue of what the application can gate on — they
 * are referenced by route middleware and Blade `@can` directives, so they stay
 * in code. Which role holds which permission is stored in the database and is
 * editable by an admin through "Manajemen Peran" (master-data.roles.index).
 */
enum Permission: string
{
    // Master data (students, teachers, classes, majors, academic years, users).
    case ManageMasterData = 'master-data.manage';
    case ManageRole = 'role.manage';

    // Attendance.
    case ViewAttendance = 'attendance.view';
    case ManageAttendance = 'attendance.manage';

    // Violations.
    case ViewViolation = 'violation.view';
    case InputViolation = 'violation.input';
    case ManageViolation = 'violation.manage';

    // Discipline points.
    case ViewPoint = 'point.view';
    case ManagePoint = 'point.manage';

    // Achievements.
    case ViewAchievement = 'achievement.view';
    case RequestAchievement = 'achievement.request';
    case ManageAchievement = 'achievement.manage';

    // Permission requests (perizinan).
    case ViewPermit = 'permit.view';
    case RequestPermit = 'permit.request';
    case ManagePermit = 'permit.manage';

    // Warning letters (surat peringatan).
    case ViewWarning = 'warning.view';
    case ManageWarning = 'warning.manage';

    // Dashboard.
    case ViewDashboard = 'dashboard.view';

    /**
     * The human-readable Indonesian label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManageMasterData => 'Kelola Master Data',
            self::ManageRole => 'Kelola Peran & Hak Akses',
            self::ViewAttendance => 'Lihat Absensi',
            self::ManageAttendance => 'Kelola Absensi',
            self::ViewViolation => 'Lihat Pelanggaran',
            self::InputViolation => 'Catat Pelanggaran',
            self::ManageViolation => 'Kelola Pelanggaran',
            self::ViewPoint => 'Lihat Poin',
            self::ManagePoint => 'Kelola Poin',
            self::ViewAchievement => 'Lihat Prestasi',
            self::RequestAchievement => 'Ajukan Prestasi',
            self::ManageAchievement => 'Kelola Prestasi',
            self::ViewPermit => 'Lihat Perizinan',
            self::RequestPermit => 'Ajukan Perizinan',
            self::ManagePermit => 'Kelola Perizinan',
            self::ViewWarning => 'Lihat Surat Peringatan',
            self::ManageWarning => 'Kelola Surat Peringatan',
            self::ViewDashboard => 'Lihat Dashboard',
        };
    }

    /**
     * A short explanation of what the permission unlocks, shown beneath the
     * label on the role editor.
     */
    public function description(): string
    {
        return match ($this) {
            self::ManageMasterData => 'Tahun ajaran, jurusan, kelas, guru, dan siswa.',
            self::ManageRole => 'Mengubah hak akses milik peran lain melalui halaman ini.',
            self::ViewAttendance => 'Membuka daftar dan rekap kehadiran.',
            self::ManageAttendance => 'Scan kiosk, koreksi status, dan pengaturan absensi.',
            self::ViewViolation => 'Membuka daftar dan detail pelanggaran.',
            self::InputViolation => 'Mencatat pelanggaran baru untuk siswa.',
            self::ManageViolation => 'Mengubah, menghapus, dan memverifikasi pelanggaran.',
            self::ViewPoint => 'Membuka rekap dan monitoring poin siswa.',
            self::ManagePoint => 'Aturan poin, penyesuaian manual, dan pengaturan poin.',
            self::ViewAchievement => 'Membuka daftar dan detail prestasi.',
            self::RequestAchievement => 'Mengajukan prestasi untuk diverifikasi.',
            self::ManageAchievement => 'Menyetujui, menolak, dan mengubah prestasi.',
            self::ViewPermit => 'Membuka daftar dan detail perizinan.',
            self::RequestPermit => 'Mengajukan izin baru.',
            self::ManagePermit => 'Menyetujui, menolak, dan mencetak perizinan.',
            self::ViewWarning => 'Membuka daftar dan detail surat peringatan.',
            self::ManageWarning => 'Menerbitkan dan mengatur ambang surat peringatan.',
            self::ViewDashboard => 'Membuka halaman dashboard setelah login.',
        };
    }

    /**
     * The module the permission belongs to, used to group the role editor.
     */
    public function group(): string
    {
        return match ($this) {
            self::ViewDashboard => 'Dashboard',
            self::ManageMasterData, self::ManageRole => 'Master Data',
            self::ViewAttendance, self::ManageAttendance => 'Kehadiran',
            self::ViewViolation, self::InputViolation, self::ManageViolation => 'Pelanggaran',
            self::ViewPoint, self::ManagePoint => 'Poin Disiplin',
            self::ViewAchievement, self::RequestAchievement, self::ManageAchievement => 'Prestasi',
            self::ViewPermit, self::RequestPermit, self::ManagePermit => 'Perizinan',
            self::ViewWarning, self::ManageWarning => 'Surat Peringatan',
        };
    }

    /**
     * All permission backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * Every case bucketed by {@see self::group()}, in declaration order.
     *
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }
}

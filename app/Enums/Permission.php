<?php

namespace App\Enums;

/**
 * Granular permissions derived from the PRD access matrix (section 3.1 / 5.4).
 *
 * Naming convention: "{module}.{action}". "manage" implies full CRUD,
 * "view" is read-only, and the remaining actions are module-specific.
 */
enum Permission: string
{
    // Master data (students, teachers, classes, majors, academic years, users).
    case ManageMasterData = 'master-data.manage';

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
     * All permission backed values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}

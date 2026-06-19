<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the nine SMARTSIS roles and their permissions per the PRD access
     * matrix (PRD section 3.1 / KAK section 5.4).
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        // Refresh the cache so newly created permissions are resolvable when
        // syncing them onto roles below.
        $registrar->forgetCachedPermissions();

        foreach ($this->matrix() as $roleValue => $permissions) {
            $role = Role::findOrCreate($roleValue);
            $role->syncPermissions($permissions);
        }
    }

    /**
     * Map each role to the permissions it should hold.
     *
     * Super Admin is granted every permission (and additionally bypasses all
     * checks via a Gate::before rule registered in AppServiceProvider).
     *
     * @return array<string, array<int, string>>
     */
    private function matrix(): array
    {
        $p = fn (PermissionEnum $permission): string => $permission->value;

        return [
            UserRole::SuperAdmin->value => PermissionEnum::values(),

            UserRole::KepalaSekolah->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ViewViolation),
                $p(PermissionEnum::ViewPoint),
                $p(PermissionEnum::ViewAchievement),
                $p(PermissionEnum::ViewPermit),
                $p(PermissionEnum::ViewWarning),
            ],

            UserRole::WakasekKesiswaan->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ViewViolation),
                $p(PermissionEnum::ViewPoint),
                $p(PermissionEnum::ViewAchievement),
                $p(PermissionEnum::ViewPermit),
                $p(PermissionEnum::ViewWarning),
            ],

            UserRole::GuruBk->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ViewViolation),
                $p(PermissionEnum::ManageViolation),
                $p(PermissionEnum::ViewPoint),
                $p(PermissionEnum::ManagePoint),
                $p(PermissionEnum::ViewAchievement),
                $p(PermissionEnum::ManageAchievement),
                $p(PermissionEnum::ViewPermit),
                $p(PermissionEnum::ViewWarning),
                $p(PermissionEnum::ManageWarning),
            ],

            UserRole::WaliKelas->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ViewViolation),
                $p(PermissionEnum::ViewPoint),
                $p(PermissionEnum::ViewAchievement),
                $p(PermissionEnum::ViewPermit),
                $p(PermissionEnum::ViewWarning),
            ],

            UserRole::GuruPiket->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ManageAttendance),
                $p(PermissionEnum::ViewViolation),
                $p(PermissionEnum::InputViolation),
                $p(PermissionEnum::ViewPoint),
                $p(PermissionEnum::ViewPermit),
                $p(PermissionEnum::ManagePermit),
            ],

            UserRole::GuruMapel->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ViewViolation),
            ],

            UserRole::Siswa->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ViewViolation),
                $p(PermissionEnum::ViewPoint),
                $p(PermissionEnum::ViewAchievement),
                $p(PermissionEnum::ViewPermit),
                $p(PermissionEnum::RequestPermit),
                $p(PermissionEnum::ViewWarning),
            ],

            UserRole::OrangTua->value => [
                $p(PermissionEnum::ViewDashboard),
                $p(PermissionEnum::ViewAttendance),
                $p(PermissionEnum::ViewViolation),
                $p(PermissionEnum::ViewPoint),
                $p(PermissionEnum::ViewAchievement),
                $p(PermissionEnum::ViewPermit),
                $p(PermissionEnum::ViewWarning),
            ],
        ];
    }
}

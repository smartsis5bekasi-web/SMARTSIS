<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The classroom face-scan kiosk runs on a shared tablet, so it gets its own
 * device account instead of staying signed in as a staff member. Create the
 * "Mode Kiosk Absensi" permission and the Kiosk role that holds only it, and
 * hand the permission to Super Admin so the locked role stays complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate(PermissionEnum::UseAttendanceKiosk->value);

        if (Role::whereName(UserRole::Kiosk->value)->doesntExist()) {
            Role::findOrCreate(UserRole::Kiosk->value)->syncPermissions([$permission]);
        }

        $superAdmin = Role::whereName(UserRole::SuperAdmin->value)->first();

        if ($superAdmin !== null && ! $superAdmin->hasPermissionTo($permission)) {
            $superAdmin->givePermissionTo($permission);
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        Role::whereName(UserRole::Kiosk->value)->delete();
        Permission::whereName(PermissionEnum::UseAttendanceKiosk->value)->delete();

        $registrar->forgetCachedPermissions();
    }
};

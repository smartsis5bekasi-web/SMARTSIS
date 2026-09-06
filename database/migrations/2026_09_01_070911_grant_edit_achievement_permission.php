<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Ubah Prestasi" shipped in the Permission enum but never reached databases
 * seeded before it existed, so no role could hold it. Create the permission
 * and hand it to the roles the PRD matrix gives it to; every other role keeps
 * whatever an admin has configured on "Manajemen Peran".
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate(PermissionEnum::EditAchievement->value);

        foreach ($this->grantees() as $role) {
            $model = Role::whereName($role->value)->first();

            if ($model !== null && ! $model->hasPermissionTo($permission)) {
                $model->givePermissionTo($permission);
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        Permission::whereName(PermissionEnum::EditAchievement->value)->delete();

        $registrar->forgetCachedPermissions();
    }

    /**
     * The roles that receive the permission, per their default matrix.
     *
     * @return array<int, UserRole>
     */
    private function grantees(): array
    {
        return [UserRole::SuperAdmin, UserRole::GuruBk, UserRole::Siswa];
    }
};

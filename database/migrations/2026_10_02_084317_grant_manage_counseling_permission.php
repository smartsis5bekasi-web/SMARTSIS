<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Catat Pemanggilan BK" is new, so databases seeded before it existed have
 * no role holding it. Create the permission and hand it to the roles the
 * default matrix gives it to; every other role keeps whatever an admin has
 * configured on "Manajemen Peran".
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate(PermissionEnum::ManageCounseling->value);

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

        Permission::whereName(PermissionEnum::ManageCounseling->value)->delete();

        $registrar->forgetCachedPermissions();
    }

    /**
     * The roles that receive the permission, per their default matrix.
     *
     * @return array<int, UserRole>
     */
    private function grantees(): array
    {
        return [UserRole::SuperAdmin, UserRole::GuruBk];
    }
};

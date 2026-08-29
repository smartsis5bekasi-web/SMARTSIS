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
     * Reset every role back to its shipped defaults instead of preserving the
     * permissions an admin configured on "Manajemen Peran".
     */
    public bool $forceDefaults = false;

    /**
     * Seed the nine SMARTSIS roles and their default permissions per the PRD
     * access matrix (PRD section 3.1 / KAK section 5.4).
     *
     * Roles that already exist keep whatever an admin has configured on
     * "Manajemen Peran" — only newly created roles receive the defaults — so
     * re-running the seeder never silently reverts a customised matrix.
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

        foreach (UserRole::cases() as $role) {
            $model = Role::whereName($role->value)->first();

            if ($model === null) {
                Role::findOrCreate($role->value)->syncPermissions($role->defaultPermissions());

                continue;
            }

            // Super Admin always holds everything, including permissions added
            // by a later release.
            if ($role->isLocked() || $this->forceDefaults) {
                $model->syncPermissions($role->defaultPermissions());
            }
        }

        $registrar->forgetCachedPermissions();
    }
}

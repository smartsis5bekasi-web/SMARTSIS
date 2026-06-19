<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('seeder creates the nine roles and every permission', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe(count(UserRole::cases()))
        ->and(Permission::count())->toBe(count(PermissionEnum::cases()));

    foreach (UserRole::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }
});

test('super admin role holds every permission', function () {
    $this->seed(RolePermissionSeeder::class);

    $role = Role::findByName(UserRole::SuperAdmin->value);

    expect($role->permissions)->toHaveCount(count(PermissionEnum::cases()));
});

test('guru bk can manage discipline but not master data', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruBk->value);

    expect($user->can(PermissionEnum::ManageViolation->value))->toBeTrue()
        ->and($user->can(PermissionEnum::ManagePoint->value))->toBeTrue()
        ->and($user->can(PermissionEnum::ManageWarning->value))->toBeTrue()
        ->and($user->can(PermissionEnum::ManageMasterData->value))->toBeFalse();
});

test('guru mapel has read-only access', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruMapel->value);

    expect($user->can(PermissionEnum::ViewAttendance->value))->toBeTrue()
        ->and($user->can(PermissionEnum::ManageAttendance->value))->toBeFalse()
        ->and($user->can(PermissionEnum::ManagePoint->value))->toBeFalse()
        ->and($user->can(PermissionEnum::ManageMasterData->value))->toBeFalse();
});

test('super admin bypasses the gate for any ability', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(UserRole::SuperAdmin->value);

    expect($user->can(PermissionEnum::ManageMasterData->value))->toBeTrue()
        ->and($user->can('some.unregistered.ability'))->toBeTrue();
});

test('permission middleware blocks users lacking the permission', function () {
    $this->seed(RolePermissionSeeder::class);

    Route::get('/_test/master-data', fn () => 'ok')
        ->middleware(['auth', 'permission:'.PermissionEnum::ManageMasterData->value]);

    $guru = User::factory()->create();
    $guru->assignRole(UserRole::GuruMapel->value);
    $this->actingAs($guru)->get('/_test/master-data')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SuperAdmin->value);
    $this->actingAs($admin)->get('/_test/master-data')->assertOk();
});

test('public registration is disabled', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

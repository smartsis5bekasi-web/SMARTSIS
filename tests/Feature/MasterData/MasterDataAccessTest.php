<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * A verified Super Admin, who bypasses every gate.
 */
function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::SuperAdmin->value);

    return $user;
}

$routes = [
    'master-data.academic-years',
    'master-data.majors',
    'master-data.classrooms',
    'master-data.teachers',
];

test('guests are redirected from master data pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with($routes);

test('users without the manage permission are forbidden', function (string $route) {
    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruMapel->value);

    $this->actingAs($user)->get(route($route))->assertForbidden();
})->with($routes);

test('the super admin can open every master data page', function (string $route) {
    $this->actingAs(adminUser())->get(route($route))->assertOk();
})->with($routes);

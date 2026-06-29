<?php

use App\Enums\UserRole;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the point settings can be updated', function () {
    $this->actingAs(userWithRole(UserRole::SuperAdmin));

    Livewire::test('pages::attendance.point.settings')
        ->set('initial_point', 100)
        ->set('target_point', 120)
        ->set('min_point', 40)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('attendance.points'));

    $this->assertDatabaseHas('point_settings', [
        'initial_point' => 100,
        'target_point' => 120,
        'min_point' => 40,
    ]);
});

test('the minimum point cannot exceed the target', function () {
    $this->actingAs(userWithRole(UserRole::SuperAdmin));

    Livewire::test('pages::attendance.point.settings')
        ->set('target_point', 100)
        ->set('min_point', 150)
        ->call('save')
        ->assertHasErrors('min_point');
});

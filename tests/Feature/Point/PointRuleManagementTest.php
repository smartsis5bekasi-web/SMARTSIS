<?php

use App\Enums\UserRole;
use App\Models\PointRule;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guru bk can create a point rule', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::attendance.point.create')
        ->set('name', 'Juara Lomba')
        ->set('type', 'addition')
        ->set('source', 'achievement')
        ->set('point', 20)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('attendance.points'));

    $this->assertDatabaseHas('point_rules', [
        'name' => 'Juara Lomba',
        'type' => 'addition',
        'source' => 'achievement',
        'point' => 20,
    ]);
});

test('the rule name must be unique', function () {
    $this->actingAs(userWithRole(UserRole::SuperAdmin));
    PointRule::factory()->create(['name' => 'Terlambat']);

    Livewire::test('pages::attendance.point.create')
        ->set('name', 'Terlambat')
        ->set('type', 'deduction')
        ->set('source', 'violation')
        ->set('point', 2)
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('a deduction cannot use an addition-only source', function () {
    $this->actingAs(userWithRole(UserRole::SuperAdmin));

    Livewire::test('pages::attendance.point.create')
        ->set('name', 'Salah Sumber')
        ->set('type', 'deduction')
        ->set('source', 'achievement')
        ->set('point', 5)
        ->call('save')
        ->assertHasErrors('source');
});

test('a point rule can be edited', function () {
    $this->actingAs(userWithRole(UserRole::SuperAdmin));
    $rule = PointRule::factory()->deduction()->create(['point' => 2]);

    Livewire::test('pages::attendance.point.edit', ['pointRule' => $rule])
        ->set('point', 5)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('attendance.points'));

    expect($rule->fresh()->point)->toBe(5);
});

test('a point rule can be deleted', function () {
    $this->actingAs(userWithRole(UserRole::SuperAdmin));
    $rule = PointRule::factory()->create();

    Livewire::test('pages::attendance.point.index')
        ->call('delete', $rule->id);

    $this->assertDatabaseMissing('point_rules', ['id' => $rule->id]);
});

test('guru bk may open the rule create page', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('attendance.points.create'))
        ->assertOk();
});

test('viewers without manage permission are forbidden from rule pages', function (string $route) {
    $this->actingAs(userWithRole(UserRole::WaliKelas))
        ->get(route($route))
        ->assertForbidden();
})->with([
    'attendance.points.create',
    'attendance.points.settings',
]);

<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\PointSetting;
use App\Models\Student;
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

test('a new student starts with the configured initial point', function () {
    PointSetting::current()->update(['initial_point' => 85]);

    $student = Student::create([
        'name' => 'Siswa Baru',
        'nis' => '0099001122',
    ]);

    expect($student->current_point)->toBe(85);
});

test('creating a student via master data uses the configured initial point', function () {
    PointSetting::current()->update(['initial_point' => 75]);
    $classroom = Classroom::factory()->create();

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.create')
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('email', 'hafidz@smartsis.test')
        ->set('password', 'rahasia123')
        ->set('birth_date', now()->subYears(16)->format('Y-m-d'))
        ->set('classroom_id', $classroom->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Student::firstWhere('nis', '0012345678')->current_point)->toBe(75);
});

test('an explicitly provided point balance is not overridden', function () {
    PointSetting::current()->update(['initial_point' => 85]);

    $student = Student::factory()->create(['current_point' => 40]);

    expect($student->current_point)->toBe(40);
});

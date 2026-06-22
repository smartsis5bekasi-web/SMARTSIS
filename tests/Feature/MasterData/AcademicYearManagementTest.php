<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());
});

test('an academic year can be created', function () {
    Livewire::test('pages::master-data.academic-years')
        ->call('create')
        ->set('name', '2026/2027')
        ->set('started_on', '2026-07-01')
        ->set('ended_on', '2027-06-30')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $this->assertDatabaseHas('academic_years', ['name' => '2026/2027']);
});

test('the name is required and must be unique', function () {
    AcademicYear::factory()->create(['name' => '2025/2026']);

    Livewire::test('pages::master-data.academic-years')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    Livewire::test('pages::master-data.academic-years')
        ->set('name', '2025/2026')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('an academic year can be edited', function () {
    $year = AcademicYear::factory()->create(['name' => 'Lama']);

    Livewire::test('pages::master-data.academic-years')
        ->call('edit', $year->id)
        ->assertSet('name', 'Lama')
        ->set('name', 'Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($year->refresh()->name)->toBe('Baru');
});

test('activating a year deactivates the others', function () {
    $active = AcademicYear::factory()->create(['is_active' => true]);
    $target = AcademicYear::factory()->create(['is_active' => false]);

    Livewire::test('pages::master-data.academic-years')
        ->call('activate', $target->id);

    expect($target->refresh()->is_active)->toBeTrue()
        ->and($active->refresh()->is_active)->toBeFalse();
});

test('the active year cannot be deleted', function () {
    $year = AcademicYear::factory()->create(['is_active' => true]);

    Livewire::test('pages::master-data.academic-years')
        ->call('delete', $year->id);

    $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
});

test('a year with classrooms cannot be deleted', function () {
    $year = AcademicYear::factory()->create(['is_active' => false]);
    Classroom::factory()->create(['academic_year_id' => $year->id]);

    Livewire::test('pages::master-data.academic-years')
        ->call('delete', $year->id);

    $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
});

test('an unused inactive year can be deleted', function () {
    $year = AcademicYear::factory()->create(['is_active' => false]);

    Livewire::test('pages::master-data.academic-years')
        ->call('delete', $year->id);

    $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
});

<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\Student;
use App\Models\Teacher;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());
});

test('a classroom can be created with its relations', function () {
    $year = AcademicYear::factory()->create();
    $major = Major::factory()->create();
    $teacher = Teacher::factory()->create();

    Livewire::test('pages::master-data.classrooms')
        ->set('name', 'XI IPA 1')
        ->set('academic_year_id', $year->id)
        ->set('major_id', $major->id)
        ->set('homeroom_teacher_id', $teacher->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $this->assertDatabaseHas('classrooms', [
        'name' => 'XI IPA 1',
        'academic_year_id' => $year->id,
        'major_id' => $major->id,
        'homeroom_teacher_id' => $teacher->id,
    ]);
});

test('the academic year is required', function () {
    Livewire::test('pages::master-data.classrooms')
        ->set('name', 'XI IPA 1')
        ->set('academic_year_id', null)
        ->call('save')
        ->assertHasErrors(['academic_year_id' => 'required']);
});

test('the name must be unique within the same academic year', function () {
    $year = AcademicYear::factory()->create();
    Classroom::factory()->create(['name' => 'XI IPA 1', 'academic_year_id' => $year->id]);

    Livewire::test('pages::master-data.classrooms')
        ->set('name', 'XI IPA 1')
        ->set('academic_year_id', $year->id)
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('the same name is allowed in a different academic year', function () {
    $yearA = AcademicYear::factory()->create();
    $yearB = AcademicYear::factory()->create();
    Classroom::factory()->create(['name' => 'XI IPA 1', 'academic_year_id' => $yearA->id]);

    Livewire::test('pages::master-data.classrooms')
        ->set('name', 'XI IPA 1')
        ->set('academic_year_id', $yearB->id)
        ->call('save')
        ->assertHasNoErrors();
});

test('a classroom with students cannot be deleted', function () {
    $classroom = Classroom::factory()->create();
    Student::factory()->create(['classroom_id' => $classroom->id]);

    Livewire::test('pages::master-data.classrooms')
        ->call('delete', $classroom->id);

    $this->assertDatabaseHas('classrooms', ['id' => $classroom->id]);
});

test('an empty classroom can be deleted', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.classrooms')
        ->call('delete', $classroom->id);

    $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
});

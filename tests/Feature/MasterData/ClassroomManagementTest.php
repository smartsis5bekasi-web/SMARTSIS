<?php

use App\Enums\GradeLevel;
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

    Livewire::test('pages::master-data.classrooms.create')
        ->set('name', 'XI IPA 1')
        ->set('academic_year_id', $year->id)
        ->set('major_id', $major->id)
        ->set('homeroom_teacher_id', $teacher->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.classrooms'));

    $this->assertDatabaseHas('classrooms', [
        'name' => 'XI IPA 1',
        'grade' => 11,
        'academic_year_id' => $year->id,
        'major_id' => $major->id,
        'homeroom_teacher_id' => $teacher->id,
    ]);
});

test('the academic year is required', function () {
    Livewire::test('pages::master-data.classrooms.create')
        ->set('name', 'XI IPA 1')
        ->set('academic_year_id', null)
        ->call('save')
        ->assertHasErrors(['academic_year_id' => 'required']);
});

test('the name must be unique within the same academic year', function () {
    $year = AcademicYear::factory()->create();
    Classroom::factory()->create(['name' => 'XI IPA 1', 'academic_year_id' => $year->id]);

    Livewire::test('pages::master-data.classrooms.create')
        ->set('name', 'XI IPA 1')
        ->set('academic_year_id', $year->id)
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('the same name is allowed in a different academic year', function () {
    $yearA = AcademicYear::factory()->create();
    $yearB = AcademicYear::factory()->create();
    Classroom::factory()->create(['name' => 'XI IPA 1', 'academic_year_id' => $yearA->id]);

    Livewire::test('pages::master-data.classrooms.create')
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

test('the grade is guessed from the classroom name', function (string $name, ?int $grade) {
    Livewire::test('pages::master-data.classrooms.create')
        ->set('name', $name)
        ->assertSet('grade', $grade);
})->with([
    'X' => ['X IPS 2', 10],
    'XI' => ['XI IPA 1', 11],
    'XII' => ['XII IPA 3', 12],
    'numeric' => ['12-1', 12],
    'unknown' => ['Kelas Akselerasi', null],
]);

test('a guessed grade does not overwrite one already picked', function () {
    Livewire::test('pages::master-data.classrooms.create')
        ->set('grade', 12)
        ->set('name', 'X IPA 1')
        ->assertSet('grade', 12);
});

test('the grade is required', function () {
    Livewire::test('pages::master-data.classrooms.create')
        ->set('name', 'Kelas Akselerasi')
        ->set('academic_year_id', AcademicYear::factory()->create()->id)
        ->call('save')
        ->assertHasErrors(['grade' => 'required']);
});

test('the grade can be changed when editing a classroom', function () {
    $classroom = Classroom::factory()->grade(GradeLevel::Ten)->create();

    Livewire::test('pages::master-data.classrooms.edit', ['classroom' => $classroom])
        ->assertSet('grade', 10)
        ->set('grade', 11)
        ->call('save')
        ->assertHasNoErrors();

    expect($classroom->fresh()->grade)->toBe(GradeLevel::Eleven);
});

test('the edit form suggests a grade for a classroom created before grades existed', function () {
    $classroom = Classroom::factory()->create(['name' => 'XII IPS 1', 'grade' => null]);

    Livewire::test('pages::master-data.classrooms.edit', ['classroom' => $classroom])
        ->assertSet('grade', 12);
});

<?php

use App\Enums\UserRole;
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

/*
|--------------------------------------------------------------------------
| Row actions survive search & filtering
|--------------------------------------------------------------------------
|
| The "Aksi" column looked empty as soon as a search or filter ran. The markup
| was always there — Ionicons upgrades each <ion-icon> by adding a `hydrated`
| class, and Livewire's morph patches attributes back to the server HTML, which
| never contains it, so every re-rendered icon fell back to
| `ion-icon { visibility: hidden }`. These tests pin the server side: a filtered
| table still renders its edit link and delete control.
|
*/

test('the majors table keeps its row actions while a search is active', function () {
    $major = Major::factory()->create(['name' => 'Ilmu Pengetahuan Alam']);

    Livewire::test('pages::master-data.majors')
        ->set('search', 'Pengetahuan')
        ->assertSee($major->name)
        ->assertSeeHtml(route('master-data.majors.edit', $major))
        ->assertSeeHtml('name="trash-outline"');
});

test('the academic years table keeps its row actions while a search is active', function () {
    $year = AcademicYear::factory()->create(['name' => '2031/2032']);

    Livewire::test('pages::master-data.academic-years')
        ->set('search', '2031')
        ->assertSee($year->name)
        ->assertSeeHtml(route('master-data.academic-years.edit', $year))
        ->assertSeeHtml('name="trash-outline"');
});

test('the classrooms table keeps its row actions while a filter is active', function () {
    $classroom = Classroom::factory()->create(['name' => 'XII IPA 9']);

    Livewire::test('pages::master-data.classrooms')
        ->set('search', 'IPA 9')
        ->set('majorId', (string) $classroom->major_id)
        ->assertSee($classroom->name)
        ->assertSeeHtml(route('master-data.classrooms.edit', $classroom))
        ->assertSeeHtml('name="trash-outline"');
});

test('the teachers table keeps its row actions while a search is active', function () {
    $teacher = Teacher::factory()->create(['name' => 'Budi Santoso']);
    $teacher->user->assignRole(UserRole::GuruMapel->value);

    Livewire::test('pages::master-data.teachers')
        ->set('search', 'Santoso')
        ->assertSee($teacher->name)
        ->assertSeeHtml(route('master-data.teachers.edit', $teacher))
        ->assertSeeHtml('name="trash-outline"');
});

test('the students table keeps its row actions while a search and filter are active', function () {
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create([
        'name' => 'Siswa Demo',
        'classroom_id' => $classroom->id,
        'gender' => 'L',
    ]);

    Livewire::test('pages::master-data.students.index')
        ->set('search', 'Demo')
        ->set('classroomId', (string) $classroom->id)
        ->set('gender', 'L')
        ->assertSee($student->name)
        ->assertSeeHtml(route('master-data.students.edit', $student))
        ->assertSeeHtml('name="trash-outline"');
});

test('the ionicons visibility guard stays in the stylesheet', function () {
    // Guards the CSS half of the fix above: there is no browser driver in this
    // suite, so assert the rule that keeps a morphed icon visible is still there.
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('ion-icon:not(.hydrated)');
});

/*
|--------------------------------------------------------------------------
| Edit forms preload the record
|--------------------------------------------------------------------------
*/

test('the major edit form preloads every field', function () {
    $major = Major::factory()->create();

    Livewire::test('pages::master-data.majors.edit', ['major' => $major])
        ->assertSet('name', $major->name)
        ->assertSet('code', $major->code);
});

test('the academic year edit form preloads every field', function () {
    $year = AcademicYear::factory()->create();

    Livewire::test('pages::master-data.academic-years.edit', ['year' => $year])
        ->assertSet('name', $year->name)
        ->assertSet('started_on', $year->started_on->toDateString())
        ->assertSet('ended_on', $year->ended_on->toDateString());
});

test('the classroom edit form preloads every field', function () {
    $teacher = Teacher::factory()->create();
    $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);

    Livewire::test('pages::master-data.classrooms.edit', ['classroom' => $classroom])
        ->assertSet('name', $classroom->name)
        ->assertSet('major_id', $classroom->major_id)
        ->assertSet('academic_year_id', $classroom->academic_year_id)
        ->assertSet('homeroom_teacher_id', $teacher->id);
});

test('the teacher edit form preloads every field', function () {
    $teacher = Teacher::factory()->create();
    $teacher->user->assignRole(UserRole::WaliKelas->value);

    Livewire::test('pages::master-data.teachers.edit', ['teacher' => $teacher])
        ->assertSet('name', $teacher->name)
        ->assertSet('nip', $teacher->nip)
        ->assertSet('phone', $teacher->phone)
        ->assertSet('email', $teacher->user->email)
        ->assertSet('role', UserRole::WaliKelas->value);
});

test('the student edit form preloads every field, including the relation ids', function () {
    $classroom = Classroom::factory()->create();
    $teacher = Teacher::factory()->create();
    $student = Student::factory()->create([
        'classroom_id' => $classroom->id,
        'major_id' => $classroom->major_id,
        'teacher_id' => $teacher->id,
    ]);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->assertSet('name', $student->name)
        ->assertSet('nis', $student->nis)
        ->assertSet('nisn', $student->nisn)
        ->assertSet('gender', $student->gender)
        ->assertSet('birth_date', $student->birth_date->format('Y-m-d'))
        ->assertSet('address', $student->address)
        ->assertSet('classroom_id', $classroom->id)
        ->assertSet('major_id', $classroom->major_id)
        ->assertSet('teacher_id', $teacher->id);
});

test('a student imported without a login account can still be saved', function () {
    $student = Student::factory()->create([
        'user_id' => null,
        'name' => 'Lama',
        'classroom_id' => Classroom::factory(),
    ]);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->assertSet('email', '')
        ->set('name', 'Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->refresh()->name)->toBe('Baru');
});

/*
|--------------------------------------------------------------------------
| Failed validation keeps what was already typed
|--------------------------------------------------------------------------
|
| The Blade `old()` equivalent for these Livewire forms: state lives on the
| component, so a rejected submit must leave every filled field untouched.
|
*/

test('a rejected major form keeps the input that was already filled', function () {
    Livewire::test('pages::master-data.majors.create')
        ->set('code', 'IPA')
        ->call('save')
        ->assertHasErrors(['name' => 'required'])
        ->assertSet('code', 'IPA');
});

test('a rejected academic year form keeps the input that was already filled', function () {
    Livewire::test('pages::master-data.academic-years.create')
        ->set('started_on', '2031-07-01')
        ->set('ended_on', '2032-06-30')
        ->call('save')
        ->assertHasErrors(['name' => 'required'])
        ->assertSet('started_on', '2031-07-01')
        ->assertSet('ended_on', '2032-06-30');
});

test('a rejected classroom form keeps the selected relations', function () {
    $major = Major::factory()->create();
    $year = AcademicYear::factory()->create();

    Livewire::test('pages::master-data.classrooms.create')
        ->set('major_id', $major->id)
        ->set('academic_year_id', $year->id)
        ->call('save')
        ->assertHasErrors(['name' => 'required'])
        ->assertSet('major_id', $major->id)
        ->assertSet('academic_year_id', $year->id);
});

test('a rejected teacher form keeps the input that was already filled', function () {
    Livewire::test('pages::master-data.teachers.create')
        ->set('name', 'Budi Santoso')
        ->set('nip', '198001012005011001')
        ->set('phone', '08123456789')
        ->set('role', UserRole::GuruMapel->value)
        ->call('save')
        ->assertHasErrors(['email' => 'required'])
        ->assertSet('name', 'Budi Santoso')
        ->assertSet('nip', '198001012005011001')
        ->assertSet('phone', '08123456789')
        ->assertSet('role', UserRole::GuruMapel->value);
});

test('a rejected student form keeps the input that was already filled', function () {
    $classroom = Classroom::factory()->create();
    $teacher = Teacher::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set('name', 'Siswa Demo')
        ->set('email', 'siswa@smartsis.test')
        ->set('gender', 'L')
        ->set('birth_date', now()->subYears(16)->format('Y-m-d'))
        ->set('classroom_id', $classroom->id)
        ->set('major_id', $classroom->major_id)
        ->set('teacher_id', $teacher->id)
        ->call('save')
        ->assertHasErrors(['nis' => 'required'])
        ->assertSet('name', 'Siswa Demo')
        ->assertSet('email', 'siswa@smartsis.test')
        ->assertSet('gender', 'L')
        ->assertSet('classroom_id', $classroom->id)
        ->assertSet('major_id', $classroom->major_id)
        ->assertSet('teacher_id', $teacher->id);
});

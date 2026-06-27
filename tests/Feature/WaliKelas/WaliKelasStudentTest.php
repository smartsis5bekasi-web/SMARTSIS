<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Create a wali kelas (homeroom teacher) owning a single classroom.
 *
 * @return array{0: User, 1: Classroom}
 */
function waliKelasWithClass(): array
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::WaliKelas->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $classroom = Classroom::factory()->create([
        'homeroom_teacher_id' => $teacher->id,
        'major_id' => Major::factory()->create()->id,
    ]);

    return [$user, $classroom];
}

test('the index lists only students in the wali kelas homeroom class', function () {
    [$user, $classroom] = waliKelasWithClass();
    $mine = Student::factory()->create(['classroom_id' => $classroom->id]);
    $other = Student::factory()->create(['classroom_id' => Classroom::factory()->create()->id]);

    Livewire::actingAs($user)
        ->test('pages::wali-kelas.students.index')
        ->assertSee($mine->name)
        ->assertDontSee($other->name);
});

test('creating a student auto-assigns the wali kelas teacher and class major', function () {
    [$user, $classroom] = waliKelasWithClass();

    Livewire::actingAs($user)
        ->test('pages::wali-kelas.students.create')
        ->assertSet('classroom_id', $classroom->id)
        ->set('name', 'Budi')
        ->set('nis', '0012345678')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wali-kelas.students.index'));

    $this->assertDatabaseHas('students', [
        'name' => 'Budi',
        'classroom_id' => $classroom->id,
        'teacher_id' => $user->teacher->id,
        'major_id' => $classroom->major_id,
    ]);
});

test('a wali kelas cannot edit a student outside their class', function () {
    [$user] = waliKelasWithClass();
    $outsider = Student::factory()->create(['classroom_id' => Classroom::factory()->create()->id]);

    Livewire::actingAs($user)
        ->test('pages::wali-kelas.students.edit', ['student' => $outsider])
        ->assertStatus(403);
});

test('a wali kelas cannot delete a student outside their class', function () {
    [$user] = waliKelasWithClass();
    $outsider = Student::factory()->create(['classroom_id' => Classroom::factory()->create()->id]);

    Livewire::actingAs($user)
        ->test('pages::wali-kelas.students.index')
        ->call('delete', $outsider)
        ->assertStatus(403);

    $this->assertDatabaseHas('students', ['id' => $outsider->id]);
});

test('non wali kelas users cannot access the route', function () {
    $student = User::factory()->create();
    $student->assignRole(UserRole::Siswa->value);

    $this->actingAs($student)
        ->get(route('wali-kelas.students.index'))
        ->assertForbidden();
});

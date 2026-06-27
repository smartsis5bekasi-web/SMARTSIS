<?php

use App\Models\Classroom;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());
});

test('creating a student stores the record', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.students.index'));

    $this->assertDatabaseHas('students', [
        'name' => 'Hafidz',
        'nis' => '0012345678',
        'classroom_id' => $classroom->id,
    ]);
});

test('name, nis, and classroom are required', function () {
    Livewire::test('pages::master-data.students.create')
        ->call('save')
        ->assertHasErrors([
            'name' => 'required',
            'nis' => 'required',
            'classroom_id' => 'required',
        ]);
});

test('nis must be unique', function () {
    $classroom = Classroom::factory()->create();
    Student::factory()->create(['nis' => '0012345678']);

    Livewire::test('pages::master-data.students.create')
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->call('save')
        ->assertHasErrors(['nis' => 'unique']);
});

test('uploading an avatar stores it and persists the public url', function () {
    Storage::fake('public');
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::firstWhere('nis', '0012345678');

    expect($student->avatar_url)->not->toBeNull();
    Storage::disk('public')->assertExists(Str::after($student->avatar_url, '/storage/'));
});

test('editing updates the student', function () {
    $classroom = Classroom::factory()->create();
    $newClassroom = Classroom::factory()->create();
    $student = Student::factory()->create(['name' => 'Lama', 'classroom_id' => $classroom->id]);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->assertSet('name', 'Lama')
        ->set('name', 'Baru')
        ->set('classroom_id', $newClassroom->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.students.index'));

    expect($student->refresh())
        ->name->toBe('Baru')
        ->classroom_id->toBe($newClassroom->id);
});

test('replacing the avatar deletes the previous file', function () {
    Storage::fake('public');
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->set('avatar', UploadedFile::fake()->image('first.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $firstPath = Str::after($student->refresh()->avatar_url, '/storage/');
    Storage::disk('public')->assertExists($firstPath);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->set('avatar', UploadedFile::fake()->image('second.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists(Str::after($student->refresh()->avatar_url, '/storage/'));
});

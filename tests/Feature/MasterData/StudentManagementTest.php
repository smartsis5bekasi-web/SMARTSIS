<?php

use App\Models\Classroom;
use App\Models\ParentGuardian;
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

test('creating a student with parents links them with the relationship', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->call('addParent')
        ->set('parents.0.name', 'Budi Hartono')
        ->set('parents.0.relationship', 'Ayah')
        ->set('parents.0.phone', '081234567890')
        ->call('addParent')
        ->set('parents.1.name', 'Siti Aminah')
        ->set('parents.1.relationship', 'Ibu')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::firstWhere('nis', '0012345678');

    expect($student->parents)->toHaveCount(2)
        ->and($student->parents->firstWhere('name', 'Budi Hartono')->pivot->relationship)->toBe('Ayah')
        ->and($student->parents->firstWhere('name', 'Budi Hartono')->phone)->toBe('081234567890')
        ->and($student->parents->firstWhere('name', 'Siti Aminah')->pivot->relationship)->toBe('Ibu')
        ->and($student->parents->firstWhere('name', 'Budi Hartono')->user_id)->toBeNull();
});

test('a parent row requires a name', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->call('addParent')
        ->call('save')
        ->assertHasErrors(['parents.0.name' => 'required']);
});

test('editing preloads the linked parents and updates them', function () {
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);
    $parent = ParentGuardian::factory()->withoutAccount()->create(['name' => 'Budi Lama']);
    $student->parents()->attach($parent->id, ['relationship' => 'Ayah']);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->assertSet('parents.0.name', 'Budi Lama')
        ->assertSet('parents.0.relationship', 'Ayah')
        ->set('parents.0.name', 'Budi Baru')
        ->set('parents.0.relationship', 'Wali')
        ->call('save')
        ->assertHasNoErrors();

    expect($parent->refresh()->name)->toBe('Budi Baru')
        ->and($student->parents()->first()->pivot->relationship)->toBe('Wali');
});

test('removing a parent row detaches it and deletes an orphaned record', function () {
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);
    $parent = ParentGuardian::factory()->withoutAccount()->create();
    $student->parents()->attach($parent->id, ['relationship' => 'Ibu']);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->call('removeParent', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->parents()->count())->toBe(0);
    $this->assertDatabaseMissing('parents', ['id' => $parent->id]);
});

test('a detached parent with an account or other children is kept', function () {
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);
    $sibling = Student::factory()->create();
    $withAccount = ParentGuardian::factory()->create();
    $sharedParent = ParentGuardian::factory()->withoutAccount()->create();
    $student->parents()->attach($withAccount->id, ['relationship' => 'Ayah']);
    $student->parents()->attach($sharedParent->id, ['relationship' => 'Ibu']);
    $sibling->parents()->attach($sharedParent->id, ['relationship' => 'Ibu']);

    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->call('removeParent', 1)
        ->call('removeParent', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->parents()->count())->toBe(0);
    $this->assertDatabaseHas('parents', ['id' => $withAccount->id]);
    $this->assertDatabaseHas('parents', ['id' => $sharedParent->id]);
    expect($sibling->parents()->count())->toBe(1);
});

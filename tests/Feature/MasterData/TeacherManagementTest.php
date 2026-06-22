<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());
});

test('creating a guru provisions a user, role, and teacher profile', function () {
    Livewire::test('pages::master-data.teachers')
        ->set('name', 'Pak Budi')
        ->set('nip', '198001012005011001')
        ->set('phone', '08123456789')
        ->set('role', UserRole::GuruMapel->value)
        ->set('email', 'budi@smartsis.test')
        ->set('password', 'rahasia123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $user = User::where('email', 'budi@smartsis.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole(UserRole::GuruMapel->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue();

    $this->assertDatabaseHas('teachers', [
        'user_id' => $user->id,
        'name' => 'Pak Budi',
        'nip' => '198001012005011001',
    ]);
});

test('email and password are required when creating', function () {
    Livewire::test('pages::master-data.teachers')
        ->set('name', 'Pak Budi')
        ->set('role', UserRole::GuruMapel->value)
        ->call('save')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);
});

test('the role must be a valid teacher role', function () {
    Livewire::test('pages::master-data.teachers')
        ->set('name', 'Pak Budi')
        ->set('email', 'budi@smartsis.test')
        ->set('password', 'rahasia123')
        ->set('role', UserRole::Siswa->value)
        ->call('save')
        ->assertHasErrors('role');
});

test('the email must be unique across users', function () {
    User::factory()->create(['email' => 'taken@smartsis.test']);

    Livewire::test('pages::master-data.teachers')
        ->set('name', 'Pak Budi')
        ->set('email', 'taken@smartsis.test')
        ->set('password', 'rahasia123')
        ->set('role', UserRole::GuruMapel->value)
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

test('editing updates the teacher and reassigns the role without forcing a new password', function () {
    $user = User::factory()->create(['name' => 'Lama']);
    $user->assignRole(UserRole::GuruMapel->value);
    $originalHash = $user->password;
    $teacher = Teacher::factory()->create(['user_id' => $user->id, 'name' => 'Lama']);

    Livewire::test('pages::master-data.teachers')
        ->call('edit', $teacher->id)
        ->assertSet('name', 'Lama')
        ->assertSet('role', UserRole::GuruMapel->value)
        ->set('name', 'Baru')
        ->set('role', UserRole::WaliKelas->value)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($teacher->refresh()->name)->toBe('Baru')
        ->and($user->name)->toBe('Baru')
        ->and($user->hasRole(UserRole::WaliKelas->value))->toBeTrue()
        ->and($user->hasRole(UserRole::GuruMapel->value))->toBeFalse()
        ->and($user->password)->toBe($originalHash);
});

test('providing a password on edit changes it', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruMapel->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    Livewire::test('pages::master-data.teachers')
        ->call('edit', $teacher->id)
        ->set('password', 'passwordbaru')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('passwordbaru', $user->refresh()->password))->toBeTrue();
});

test('deleting a guru removes the teacher and its login, nulling any homeroom', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::WaliKelas->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);

    Livewire::test('pages::master-data.teachers')
        ->call('delete', $teacher->id);

    $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseHas('classrooms', ['id' => $classroom->id, 'homeroom_teacher_id' => null]);
});

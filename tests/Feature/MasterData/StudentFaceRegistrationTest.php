<?php

use App\Enums\UserRole;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array<int, array<int, float>>
 */
function adminCapturedDescriptors(): array
{
    return [array_map(fn (int $i): float => $i / 128, range(1, 128))];
}

test('an admin can open the face registration page from the student data', function () {
    $student = Student::factory()->create();

    $this->actingAs(adminUser())
        ->get(route('master-data.students.show', $student))
        ->assertOk()
        ->assertSee(route('master-data.students.face', $student))
        ->assertSee('Daftarkan Wajah');

    $this->get(route('master-data.students.face', $student))
        ->assertOk()
        ->assertSee('SmartsisFace.start', false);
});

test('roles without master data access cannot register a student face', function () {
    $student = Student::factory()->create();

    $this->actingAs(userWithRole(UserRole::WaliKelas))
        ->get(route('master-data.students.face', $student))
        ->assertForbidden();
});

test('an admin registers a face for a student', function () {
    $student = Student::factory()->create(['face_descriptors' => null]);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.face', ['student' => $student])
        ->call('storeFaceDescriptors', adminCapturedDescriptors())
        ->assertRedirect(route('master-data.students.show', $student));

    expect($student->fresh()->hasRegisteredFace())->toBeTrue()
        ->and($student->fresh()->face_registered_at)->not->toBeNull();
});

test('re-registering replaces the previous face template', function () {
    $student = Student::factory()->onboarded()->create();

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.face', ['student' => $student])
        ->call('storeFaceDescriptors', adminCapturedDescriptors());

    expect($student->fresh()->face_descriptors)->toEqual(adminCapturedDescriptors());
});

test('malformed descriptors from the admin page are rejected', function () {
    $student = Student::factory()->create(['face_descriptors' => null]);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.face', ['student' => $student])
        ->call('storeFaceDescriptors', [array_fill(0, 64, 0.5)])
        ->assertNoRedirect();

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('the student list can be filtered by face registration', function () {
    $registered = Student::factory()->onboarded()->create(['name' => 'Budi Terdaftar']);
    $missing = Student::factory()->create(['name' => 'Sari Belum', 'face_descriptors' => null]);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.index')
        ->set('face', 'missing')
        ->assertSee($missing->name)
        ->assertDontSee($registered->name)
        ->set('face', 'registered')
        ->assertSee($registered->name)
        ->assertDontSee($missing->name);
});

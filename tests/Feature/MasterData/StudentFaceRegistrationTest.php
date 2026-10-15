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

test('a face registered from the admin page is recorded as staff-verified', function () {
    $admin = adminUser();
    $student = Student::factory()->create(['user_id' => userWithRole(UserRole::Siswa)->id]);

    $this->actingAs($admin);

    Livewire::test('pages::master-data.students.face', ['student' => $student])
        ->call('storeFaceDescriptors', adminCapturedDescriptors());

    expect($student->fresh())
        ->face_registered_by->toBe($admin->id)
        ->hasStaffVerifiedFace()->toBeTrue()
        ->needsOnboarding()->toBeFalse();
});

test('the admin page refuses a face that belongs to another student', function () {
    Student::factory()->create(['name' => 'Pemilik Wajah', 'face_descriptors' => adminCapturedDescriptors(), 'face_registered_at' => now()]);
    $student = Student::factory()->create(['face_descriptors' => null]);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.face', ['student' => $student])
        ->call('storeFaceDescriptors', adminCapturedDescriptors())
        ->assertNoRedirect()
        ->assertSet('faceConflict.name', 'Pemilik Wajah')
        ->assertSee('Pemilik Wajah');

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('the admin page can move a self-registered face to the student it belongs to', function () {
    $owner = Student::factory()->create(['face_descriptors' => adminCapturedDescriptors(), 'face_registered_at' => now()]);
    $student = Student::factory()->create(['face_descriptors' => null]);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.face', ['student' => $student])
        ->call('storeFaceDescriptors', adminCapturedDescriptors())
        ->assertSee(route('master-data.students.face', $owner->id))
        ->call('confirmFaceConflict')
        ->assertRedirect(route('master-data.students.show', $student));

    expect($student->fresh()->hasStaffVerifiedFace())->toBeTrue()
        ->and($owner->fresh()->hasRegisteredFace())->toBeFalse();
});

test('self-registered faces can be listed for re-verification', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $self = Student::factory()->onboarded()->create(['name' => 'Daftar Sendiri', 'user_id' => $siswa->id, 'face_registered_by' => $siswa->id]);
    $legacy = Student::factory()->onboarded()->create(['name' => 'Data Lama']);
    $verified = Student::factory()->onboarded()->create(['name' => 'Sudah Diverifikasi', 'face_registered_by' => adminUser()->id]);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.students.index')
        ->set('face', 'unverified')
        ->assertSee($self->name)
        ->assertSee($legacy->name)
        ->assertDontSee($verified->name);
});

test('the student detail shows who verified the face', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $student = Student::factory()->onboarded()->create(['face_registered_by' => $staff->id]);

    $this->actingAs(adminUser())
        ->get(route('master-data.students.show', $student))
        ->assertOk()
        ->assertSee(__('Wajah Terverifikasi · :name', ['name' => $staff->name]));
});

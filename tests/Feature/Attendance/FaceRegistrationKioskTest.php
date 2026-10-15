<?php

use App\Enums\FaceConflictResolution;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * One 128-float sample, distinct per seed so two students never look alike.
 *
 * @return array<int, array<int, float>>
 */
function kioskFaceSample(int $seed = 1): array
{
    return [array_map(fn (int $i): float => sin($i * $seed) / 2, range(1, 128))];
}

/**
 * A wali kelas account together with the class they hold.
 *
 * @return array{0: User, 1: Classroom}
 */
function homeroomTeacher(): array
{
    $teacher = Teacher::factory()->create();
    $teacher->user->assignRole(UserRole::WaliKelas->value);

    return [$teacher->user, Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id])];
}

test('the kiosk links to the face registration mode', function () {
    $this->actingAs(userWithRole(UserRole::Kiosk))
        ->get(route('attendance.absensi.kiosk'))
        ->assertOk()
        ->assertSee(route('attendance.face-registration'), false);
});

test('staff who may register faces are the operator straight away', function () {
    $staff = userWithRole(UserRole::GuruPiket);

    $this->actingAs($staff)
        ->get(route('attendance.face-registration'))
        ->assertOk()
        ->assertDontSee(__('Mode registrasi wajah dikunci'));

    Livewire::actingAs($staff)
        ->test('pages::attendance.face-registration')
        ->assertSet('operatorId', $staff->id)
        ->assertSet('unlockedUntil', null);
});

test('a kiosk device account opens the page locked', function () {
    $this->actingAs(userWithRole(UserRole::Kiosk))
        ->get(route('attendance.face-registration'))
        ->assertOk()
        ->assertSee(__('Mode registrasi wajah dikunci'));
});

test('students and roles without the permission cannot open the page', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('attendance.face-registration'))
        ->assertForbidden();
})->with([
    'guru mapel' => [UserRole::GuruMapel],
    'orang tua' => [UserRole::OrangTua],
]);

test('staff credentials unlock a kiosk device session', function () {
    $staff = userWithRole(UserRole::GuruBk);

    Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->assertSet('operatorId', null)
        ->set('unlockEmail', $staff->email)
        ->set('unlockPassword', 'password')
        ->call('unlock')
        ->assertHasNoErrors()
        ->assertSet('operatorId', $staff->id)
        ->assertSet('unlockPassword', '')
        ->assertSee(__('Petugas: :name', ['name' => $staff->name]));
});

test('a wrong password or an account without the permission does not unlock', function () {
    $guruMapel = userWithRole(UserRole::GuruMapel);
    $staff = userWithRole(UserRole::GuruPiket);

    Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('unlockEmail', $staff->email)
        ->set('unlockPassword', 'salah')
        ->call('unlock')
        ->assertHasErrors('unlockEmail')
        ->assertSet('operatorId', null)
        ->set('unlockEmail', $guruMapel->email)
        ->set('unlockPassword', 'password')
        ->call('unlock')
        ->assertHasErrors('unlockEmail')
        ->assertSet('operatorId', null);
});

test('a deactivated staff account cannot unlock', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $staff->update(['is_active' => false]);

    Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('unlockEmail', $staff->email)
        ->set('unlockPassword', 'password')
        ->call('unlock')
        ->assertHasErrors('unlockEmail')
        ->assertSet('operatorId', null);
});

test('unlock attempts are throttled', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $component = Livewire::actingAs(userWithRole(UserRole::Kiosk))->test('pages::attendance.face-registration');

    foreach (range(1, 5) as $attempt) {
        $component->set('unlockEmail', $staff->email)->set('unlockPassword', 'salah')->call('unlock');
    }

    $component->set('unlockPassword', 'password')
        ->call('unlock')
        ->assertHasErrors('unlockEmail')
        ->assertSet('operatorId', null);
});

test('the operator cannot be forged from the browser', function () {
    $staff = userWithRole(UserRole::GuruPiket);

    Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('operatorId', $staff->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('the selected student cannot be forged from the browser', function () {
    $student = Student::factory()->create();

    Livewire::actingAs(userWithRole(UserRole::GuruPiket))
        ->test('pages::attendance.face-registration')
        ->set('selectedStudentId', $student->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('registering at the kiosk stores a staff-verified face and completes onboarding', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $student = Student::factory()->create(['user_id' => userWithRole(UserRole::Siswa)->id]);

    Livewire::actingAs($staff)
        ->test('pages::attendance.face-registration')
        ->assertDontSee('SmartsisFace.start', false)
        ->call('selectStudent', $student->id)
        ->assertSet('selectedStudentId', $student->id)
        ->assertSee('SmartsisFace.start', false)
        ->assertSee($student->name)
        ->call('storeFaceDescriptors', kioskFaceSample())
        ->assertSet('selectedStudentId', null)
        ->assertDispatched('swal', fn (string $name, array $params): bool => $params['icon'] === 'success');

    $student->refresh();

    expect($student->hasRegisteredFace())->toBeTrue()
        ->and($student->face_registered_by)->toBe($staff->id)
        ->and($student->hasStaffVerifiedFace())->toBeTrue()
        ->and($student->nisn_verified_at)->not->toBeNull()
        ->and($student->onboarded_at)->not->toBeNull()
        ->and($student->needsOnboarding())->toBeFalse();
});

/**
 * The given sample nudged by exactly `$distance` (Euclidean) on one value.
 *
 * @param  array<int, array<int, float>>  $sample
 * @return array<int, array<int, float>>
 */
function lookAlikeOf(array $sample, float $distance): array
{
    $sample[0][0] += $distance;

    return $sample;
}

test('a face already enrolled on another student is held back next to their photo', function () {
    Student::factory()->create(['name' => 'Siswa Pertama', 'face_descriptors' => kioskFaceSample(7), 'face_registered_at' => now()]);
    $student = Student::factory()->create();

    Livewire::actingAs(userWithRole(UserRole::GuruPiket))
        ->test('pages::attendance.face-registration')
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', kioskFaceSample(7))
        ->assertSet('selectedStudentId', $student->id)
        ->assertSet('faceConflict.name', 'Siswa Pertama')
        ->assertNotDispatched('face-capture-rejected')
        ->assertSee(__('Wajah mirip dengan siswa lain'))
        ->assertSee('Siswa Pertama');

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('staff can move a self-registered face to the student in front of the camera', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $ownerUser = userWithRole(UserRole::Siswa);
    $owner = Student::factory()->create(['user_id' => $ownerUser->id, 'face_descriptors' => kioskFaceSample(7), 'face_registered_at' => now(), 'face_registered_by' => $ownerUser->id]);
    $student = Student::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::attendance.face-registration')
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', kioskFaceSample(7))
        ->assertSet('faceConflict.resolution', FaceConflictResolution::Transfer->value)
        ->assertSee(__('Pindahkan ke :student', ['student' => $student->name]))
        ->call('confirmFaceConflict')
        ->assertSet('faceConflict', null)
        ->assertSet('selectedStudentId', null)
        ->assertDispatched('swal', fn (string $name, array $params): bool => $params['icon'] === 'success');

    expect($student->fresh())
        ->hasStaffVerifiedFace()->toBeTrue()
        ->face_registered_by->toBe($staff->id)
        ->and($owner->fresh())
        ->hasRegisteredFace()->toBeFalse()
        ->face_registered_by->toBeNull();
});

test('staff can keep two look-alike students that were both verified in person', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $owner = Student::factory()->create(['face_descriptors' => kioskFaceSample(7), 'face_registered_at' => now(), 'face_registered_by' => $staff->id]);
    $student = Student::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::attendance.face-registration')
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', lookAlikeOf(kioskFaceSample(7), 0.45))
        ->assertSet('faceConflict.resolution', FaceConflictResolution::KeepBoth->value)
        ->call('confirmFaceConflict')
        ->assertSet('faceConflict', null)
        ->assertSet('selectedStudentId', null);

    expect($student->fresh()->hasStaffVerifiedFace())->toBeTrue()
        ->and($owner->fresh()->face_descriptors)->toEqual(kioskFaceSample(7));
});

test('a near-identical face already verified by staff cannot be kept twice', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    Student::factory()->create(['face_descriptors' => kioskFaceSample(7), 'face_registered_at' => now(), 'face_registered_by' => $staff->id]);
    $student = Student::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::attendance.face-registration')
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', lookAlikeOf(kioskFaceSample(7), 0.2))
        ->assertSet('faceConflict.resolution', null)
        ->assertDontSee(__('Orang berbeda, tetap simpan'))
        ->call('confirmFaceConflict')
        ->assertDispatched('face-capture-rejected')
        ->assertSet('selectedStudentId', $student->id);

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('a capture close to two students is never resolvable', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    Student::factory()->create(['face_descriptors' => lookAlikeOf(kioskFaceSample(7), 0.42), 'face_registered_at' => now(), 'face_registered_by' => $staff->id]);
    Student::factory()->create(['face_descriptors' => lookAlikeOf(kioskFaceSample(7), -0.44), 'face_registered_at' => now(), 'face_registered_by' => $staff->id]);
    $student = Student::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::attendance.face-registration')
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', kioskFaceSample(7))
        ->assertSet('faceConflict.resolution', null);
});

test('cancelling the look-alike dialog brings the camera back', function () {
    Student::factory()->create(['face_descriptors' => kioskFaceSample(7), 'face_registered_at' => now()]);
    $student = Student::factory()->create();

    Livewire::actingAs(userWithRole(UserRole::GuruPiket))
        ->test('pages::attendance.face-registration')
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', kioskFaceSample(7))
        ->call('dismissFaceConflict')
        ->assertSet('faceConflict', null)
        ->assertSet('pendingCaptureKey', null)
        ->assertDispatched('face-capture-rejected')
        ->call('confirmFaceConflict')
        ->assertSet('selectedStudentId', $student->id);

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('the dialog can switch to the other student to retake their face', function () {
    $owner = Student::factory()->create(['face_descriptors' => kioskFaceSample(7), 'face_registered_at' => now()]);
    $student = Student::factory()->create();

    Livewire::actingAs(userWithRole(UserRole::GuruPiket))
        ->test('pages::attendance.face-registration')
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', kioskFaceSample(7))
        ->assertSee(__('Daftarkan ulang wajah :name', ['name' => $owner->name]))
        ->call('reRegisterConflictOwner')
        ->assertSet('selectedStudentId', $owner->id)
        ->assertSet('faceConflict', null);
});

test('the look-alike decision cannot be forged from the browser', function () {
    Livewire::actingAs(userWithRole(UserRole::GuruPiket))
        ->test('pages::attendance.face-registration')
        ->set('faceConflict', ['owner_id' => 1, 'resolution' => 'keep-both']);
})->throws(CannotUpdateLockedPropertyException::class);

test('re-registering the same student replaces their own face without a duplicate error', function () {
    $student = Student::factory()->create(['face_descriptors' => kioskFaceSample(3), 'face_registered_at' => now()]);

    Livewire::actingAs(userWithRole(UserRole::GuruPiket))
        ->test('pages::attendance.face-registration')
        ->set('onlyMissing', false)
        ->call('selectStudent', $student->id)
        ->call('storeFaceDescriptors', kioskFaceSample(3))
        ->assertNotDispatched('face-capture-rejected');

    expect($student->fresh()->hasStaffVerifiedFace())->toBeTrue();
});

test('a wali kelas only sees and registers students of their own class', function () {
    [$waliKelas, $homeroom] = homeroomTeacher();
    $own = Student::factory()->create(['name' => 'Siswa Binaan', 'classroom_id' => $homeroom->id]);
    $other = Student::factory()->create(['name' => 'Siswa Kelas Lain', 'classroom_id' => Classroom::factory()->create()->id]);

    $component = Livewire::actingAs($waliKelas)
        ->test('pages::attendance.face-registration')
        ->assertSee('Siswa Binaan')
        ->assertDontSee('Siswa Kelas Lain');

    expect(fn () => $component->call('selectStudent', $other->id))->toThrow(ModelNotFoundException::class);

    $component->call('selectStudent', $own->id)
        ->call('storeFaceDescriptors', kioskFaceSample())
        ->assertSet('selectedStudentId', null);

    expect($own->fresh()->face_registered_by)->toBe($waliKelas->id)
        ->and($other->fresh()->hasRegisteredFace())->toBeFalse();
});

test('a wali kelas unlocking a kiosk tablet keeps the homeroom scope', function () {
    [$waliKelas, $homeroom] = homeroomTeacher();
    Student::factory()->create(['name' => 'Siswa Binaan', 'classroom_id' => $homeroom->id]);
    Student::factory()->create(['name' => 'Siswa Kelas Lain', 'classroom_id' => Classroom::factory()->create()->id]);

    Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('unlockEmail', $waliKelas->email)
        ->set('unlockPassword', 'password')
        ->call('unlock')
        ->assertSee('Siswa Binaan')
        ->assertDontSee('Siswa Kelas Lain');
});

test('the list hides registered students until asked and filters by class and search', function () {
    $classroom = Classroom::factory()->create();
    Student::factory()->create(['name' => 'Belum Daftar', 'classroom_id' => $classroom->id]);
    Student::factory()->create(['name' => 'Sudah Daftar', 'classroom_id' => $classroom->id, 'face_descriptors' => kioskFaceSample(), 'face_registered_at' => now()]);
    Student::factory()->create(['name' => 'Kelas Lain Belum']);

    Livewire::actingAs(userWithRole(UserRole::GuruPiket))
        ->test('pages::attendance.face-registration')
        ->set('classroomId', $classroom->id)
        ->assertSee('Belum Daftar')
        ->assertDontSee('Sudah Daftar')
        ->assertDontSee('Kelas Lain Belum')
        ->assertSee(__(':registered dari :total wajah terdaftar', ['registered' => 1, 'total' => 2]))
        ->set('onlyMissing', false)
        ->assertSee('Sudah Daftar')
        ->set('search', 'Sudah')
        ->assertDontSee('Belum Daftar');
});

test('a credential unlock lapses after fifteen idle minutes', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $student = Student::factory()->create();

    $component = Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('unlockEmail', $staff->email)
        ->set('unlockPassword', 'password')
        ->call('unlock')
        ->call('selectStudent', $student->id);

    $this->travel(16)->minutes();

    $component->call('storeFaceDescriptors', kioskFaceSample())
        ->assertSet('operatorId', null)
        ->assertDispatched('face-capture-rejected');

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('a registration keeps a credential unlock alive', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    [$first, $second] = Student::factory()->count(2)->create();

    $component = Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('unlockEmail', $staff->email)
        ->set('unlockPassword', 'password')
        ->call('unlock');

    $this->travel(10)->minutes();
    $component->call('selectStudent', $first->id)->call('storeFaceDescriptors', kioskFaceSample(1));

    $this->travel(10)->minutes();
    $component->call('selectStudent', $second->id)->call('storeFaceDescriptors', kioskFaceSample(2));

    expect($second->fresh()->face_registered_by)->toBe($staff->id);
});

test('locking ends a credential unlock', function () {
    $staff = userWithRole(UserRole::GuruPiket);

    Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('unlockEmail', $staff->email)
        ->set('unlockPassword', 'password')
        ->call('unlock')
        ->call('lock')
        ->assertSet('operatorId', null)
        ->assertSee(__('Mode registrasi wajah dikunci'));
});

test('the operator losing the permission mid-session stops registrations', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $student = Student::factory()->create();

    $component = Livewire::actingAs(userWithRole(UserRole::Kiosk))
        ->test('pages::attendance.face-registration')
        ->set('unlockEmail', $staff->email)
        ->set('unlockPassword', 'password')
        ->call('unlock')
        ->call('selectStudent', $student->id);

    $staff->update(['is_active' => false]);

    $component->call('storeFaceDescriptors', kioskFaceSample())
        ->assertSet('operatorId', null);

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('the upgrade migration grants the permission to the default roles only', function () {
    $waliKelas = Role::findByName(UserRole::WaliKelas->value);
    $waliKelas->revokePermissionTo(Permission::RegisterStudentFace->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    (require database_path('migrations/2026_10_13_052813_grant_register_face_permission.php'))->up();

    expect($waliKelas->fresh()->hasPermissionTo(Permission::RegisterStudentFace->value))->toBeTrue()
        ->and(Role::findByName(UserRole::GuruMapel->value)->hasPermissionTo(Permission::RegisterStudentFace->value))->toBeFalse()
        ->and(Role::findByName(UserRole::Kiosk->value)->hasPermissionTo(Permission::RegisterStudentFace->value))->toBeFalse();
});

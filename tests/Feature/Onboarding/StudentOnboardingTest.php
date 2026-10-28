<?php

use App\Actions\Student\RegisterStudentFace;
use App\Enums\UserRole;
use App\Exceptions\DuplicateFaceException;
use App\Models\AttendanceSetting;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Most of this file walks the self-registration flow (the shipped default),
| so it is switched on explicitly here; the tests at the bottom switch it
| off to cover in-person registration.
*/
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    AttendanceSetting::current()->update(['allow_self_face_registration' => true]);
});

function disallowSelfFaceRegistration(): void
{
    AttendanceSetting::current()->update(['allow_self_face_registration' => false]);
}

/**
 * @return array<int, array<int, float>>
 */
function fakeFaceDescriptors(): array
{
    return array_fill(0, 3, array_map(fn (int $i): float => $i / 128, range(1, 128)));
}

/**
 * A tiny but valid 1×1 JPEG as the camera snapshot data URL.
 */
function fakeFaceSnapshot(): string
{
    return 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==';
}

test('a siswa who has not onboarded is redirected from the dashboard to onboarding', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));
});

test('a siswa without a linked student record is also redirected to onboarding', function () {
    $this->actingAs(userWithRole(UserRole::Siswa))
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));
});

test('an onboarded siswa is not redirected', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('non-siswa roles are never redirected to onboarding', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('dashboard'))
        ->assertOk();
});

test('a siswa can open the onboarding page', function () {
    $this->actingAs(userWithRole(UserRole::Siswa))
        ->get(route('onboarding'))
        ->assertOk()
        ->assertSee('Verifikasi NISN')
        ->assertSee('Registrasi Wajah')
        ->assertSee('Konfirmasi Data');
});

test('the onboarding page redirects non-siswa users to the dashboard', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('onboarding'))
        ->assertRedirect(route('dashboard'));
});

test('the onboarding page redirects an already onboarded siswa to the dashboard', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertRedirect(route('dashboard'));
});

test('verifying the correct nisn of the linked student advances to the face step', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id, 'nisn' => '0011223344']);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->set('nisn', '0011223344')
        ->call('verifyNisn')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    expect($student->fresh()->hasVerifiedNisn())->toBeTrue();
});

test('verifying the nisn of an unclaimed student record links it to the account', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => null, 'nisn' => '0099887766']);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->set('nisn', '0099887766')
        ->call('verifyNisn')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    expect($student->fresh()->user_id)->toBe($user->id);
});

test('an unknown nisn shows an error and stays on step one', function () {
    $this->actingAs(userWithRole(UserRole::Siswa));

    Livewire::test('pages::onboarding.index')
        ->set('nisn', '9999999999')
        ->call('verifyNisn')
        ->assertHasErrors('nisn')
        ->assertSet('step', 1);
});

test('a nisn already claimed by another account is rejected', function () {
    $otherUser = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $otherUser->id, 'nisn' => '0055667788']);

    $this->actingAs(userWithRole(UserRole::Siswa));

    Livewire::test('pages::onboarding.index')
        ->set('nisn', '0055667788')
        ->call('verifyNisn')
        ->assertHasErrors('nisn')
        ->assertSet('step', 1);
});

test('a nisn that does not match the account\'s own student record is rejected', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id, 'nisn' => '0011111111']);
    $other = Student::factory()->create(['user_id' => null, 'nisn' => '0022222222']);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->set('nisn', '0022222222')
        ->call('verifyNisn')
        ->assertHasErrors('nisn');

    expect($other->fresh()->user_id)->toBeNull();
});

test('valid face descriptors are stored and advance to the confirmation step', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', fakeFaceDescriptors())
        ->assertSet('step', 3);

    $student->refresh();
    expect($student->hasRegisteredFace())->toBeTrue()
        ->and($student->face_descriptors)->toHaveCount(3)
        ->and($student->face_descriptors[0])->toHaveCount(128)
        ->and($student->face_registered_at)->not->toBeNull()
        ->and($student->onboarded_at)->toBeNull();
});

test('the face snapshot is stored as the student profile photo', function () {
    Storage::fake('public');

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id, 'avatar_url' => null]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', fakeFaceDescriptors(), fakeFaceSnapshot())
        ->assertSet('step', 3);

    $student->refresh();
    expect($student->avatar_url)->not->toBeNull();

    $path = str_replace(Storage::url(''), '', $student->avatar_url);
    Storage::disk('public')->assertExists($path);
});

test('an invalid snapshot is ignored while the face template is still stored', function (?string $snapshot) {
    Storage::fake('public');

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id, 'avatar_url' => null]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', fakeFaceDescriptors(), $snapshot)
        ->assertSet('step', 3);

    $student->refresh();
    expect($student->hasRegisteredFace())->toBeTrue()
        ->and($student->avatar_url)->toBeNull();
})->with([
    'missing snapshot' => [null],
    'not a data url' => ['hello-world'],
    'not an image payload' => ['data:image/jpeg;base64,'.base64_encode('not-an-image')],
]);

test('face registration is rejected before the nisn is verified', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', fakeFaceDescriptors())
        ->assertStatus(403);
});

test('malformed face descriptors are rejected', function (array $descriptors) {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', $descriptors)
        ->assertSet('step', 2);

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
})->with([
    'no samples' => [[]],
    'too many samples' => [array_fill(0, 4, array_fill(0, 128, 0.5))],
    'wrong dimension' => [array_fill(0, 3, array_fill(0, 64, 0.5))],
    'non numeric values' => [array_fill(0, 3, array_fill(0, 128, 'x'))],
]);

test('a single face sample is enough to register', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', [array_map(fn (int $i): float => $i / 128, range(1, 128))])
        ->assertSet('step', 3);

    expect($student->fresh()->hasRegisteredFace())->toBeTrue()
        ->and($student->fresh()->face_descriptors)->toHaveCount(1);
});

test('onboarding cannot be completed without a registered face', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2)
        ->assertDontSee('Lewati dulu')
        ->call('completeOnboarding')
        ->assertSet('step', 2)
        ->assertNoRedirect();

    expect($student->fresh()->onboarded_at)->toBeNull();
});

test('an onboarded siswa without a face template is sent back to register one', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'onboarded_at' => now(),
        'face_descriptors' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));

    $this->get(route('onboarding'))->assertOk();

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2)
        ->call('storeFaceDescriptors', fakeFaceDescriptors())
        ->assertSet('step', 3)
        ->call('completeOnboarding')
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk();
});

test('with the shipped default an onboarded siswa without a face is sent back to register one', function () {
    AttendanceSetting::query()->delete();

    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'onboarded_at' => now(),
        'face_descriptors' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2)
        ->assertSee('SmartsisFace.start', false);
});

test('an onboarded siswa without a face can skip it for this session and still use the app', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'onboarded_at' => now(),
        'face_descriptors' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2)
        ->assertSee(__('Lewati, Daftar Nanti'))
        ->call('deferFaceRegistration')
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Wajah Anda belum terdaftar'))
        ->assertSee(__('Daftarkan Wajah Sekarang'));

    $this->get(route('attendance.absensi'))->assertOk();

    // A new login starts a new session, which asks for the face again.
    $this->flushSession();

    $this->get(route('dashboard'))->assertRedirect(route('onboarding'));
});

test('a new siswa whose camera never captures a face can skip it, finish onboarding and absen', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2)
        ->assertSee(__('Lewati, Daftar Nanti'))
        ->call('deferFaceRegistration')
        ->assertSet('step', 3)
        ->call('completeOnboarding')
        ->assertRedirect(route('dashboard'));

    expect($student->fresh())
        ->onboarded_at->not->toBeNull()
        ->hasRegisteredFace()->toBeFalse();

    $this->get(route('attendance.absensi'))->assertOk();
});

test('a siswa who already has a face is not offered the skip', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'face_descriptors' => fakeFaceDescriptors(),
        'face_registered_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('retakeFace')
        ->assertSet('step', 2)
        ->assertDontSee(__('Lewati, Daftar Nanti'))
        ->call('deferFaceRegistration')
        ->assertForbidden();
});

test('a new siswa whose face is refused as another student\'s can finish onboarding without it', function () {
    Student::factory()->create(['face_descriptors' => fakeFaceDescriptors(), 'face_registered_at' => now()]);

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', fakeFaceDescriptors())
        ->assertSet('step', 2)
        ->assertSee(__('Lewati, Daftar Nanti'))
        ->call('deferFaceRegistration')
        ->assertSet('step', 3)
        ->call('completeOnboarding')
        ->assertRedirect(route('dashboard'));

    expect($student->fresh())
        ->onboarded_at->not->toBeNull()
        ->hasRegisteredFace()->toBeFalse();

    $this->get(route('dashboard'))->assertOk();
});

test('completing onboarding stamps the student and redirects to the dashboard', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'face_descriptors' => fakeFaceDescriptors(),
        'face_registered_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 3)
        ->call('completeOnboarding')
        ->assertRedirect(route('dashboard'));

    expect($student->fresh()->onboarded_at)->not->toBeNull();
});

test('onboarding cannot be completed before the nisn is verified', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('completeOnboarding')
        ->assertStatus(403);
});

test('a linked student always starts at the nisn step until it is verified', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 1);
});

test('a nisn-verified student without a face resumes at the face step', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2);
});

test('the confirmation step offers a way back to retake the face photo', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'face_descriptors' => fakeFaceDescriptors(),
        'face_registered_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 3)
        ->assertSee('Ulangi Foto')
        ->call('retakeFace')
        ->assertSet('step', 2);
});

test('retaking the photo keeps the stored face template until a new one is captured', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'face_descriptors' => fakeFaceDescriptors(),
        'face_registered_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('retakeFace')
        ->assertSet('step', 2)
        ->assertSee('Batal')
        ->call('cancelRetakeFace')
        ->assertSet('step', 3);

    expect($student->fresh()->hasRegisteredFace())->toBeTrue();
});

test('a retaken photo replaces the stored face template and profile photo', function () {
    Storage::fake('public');

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'face_descriptors' => fakeFaceDescriptors(),
        'face_registered_at' => now(),
        'avatar_url' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('retakeFace')
        ->call('storeFaceDescriptors', [array_fill(0, 128, 0.25)], fakeFaceSnapshot())
        ->assertSet('step', 3);

    $student->refresh();
    expect($student->face_descriptors)->toHaveCount(1)
        ->and($student->avatar_url)->not->toBeNull();
});

test('the retake step cannot be cancelled when no face is registered yet', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2)
        ->assertDontSee('Batal')
        ->call('cancelRetakeFace')
        ->assertStatus(403);
});

test('the face step cannot be reopened before the nisn is verified', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('retakeFace')
        ->assertStatus(403);
});

test('a face already enrolled on another student is refused without naming them', function () {
    Student::factory()->create(['name' => 'Teman Sekelas', 'face_descriptors' => fakeFaceDescriptors(), 'face_registered_at' => now()]);

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', fakeFaceDescriptors())
        ->assertSet('step', 2)
        ->assertDispatched('face-capture-rejected')
        ->assertDispatched('swal', fn (string $name, array $params): bool => ! str_contains($params['text'] ?? '', 'Teman Sekelas'));

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('a self-captured face that only resembles another student is accepted', function () {
    $resemblance = fakeFaceDescriptors();
    $resemblance[0][0] += 0.45;
    Student::factory()->create(['face_descriptors' => [$resemblance[0]], 'face_registered_at' => now()]);

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', [fakeFaceDescriptors()[0]])
        ->assertNotDispatched('face-capture-rejected')
        ->assertSet('step', 3);

    expect($student->fresh()->hasRegisteredFace())->toBeTrue();
});

test('staff still review a face that only resembles another student', function () {
    $resemblance = fakeFaceDescriptors();
    $resemblance[0][0] += 0.45;
    Student::factory()->create(['face_descriptors' => [$resemblance[0]], 'face_registered_at' => now()]);

    $student = Student::factory()->create();

    expect(fn () => app(RegisterStudentFace::class)->handle($student, [fakeFaceDescriptors()[0]], null, userWithRole(UserRole::WaliKelas)))
        ->toThrow(DuplicateFaceException::class);
});

test('a self-registered face records the student as its source', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', fakeFaceDescriptors());

    expect($student->fresh())
        ->face_registered_by->toBe($user->id)
        ->hasStaffVerifiedFace()->toBeFalse();
});

test('a face verified by staff cannot be retaken from onboarding', function () {
    $staff = userWithRole(UserRole::GuruPiket);
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create([
        'user_id' => $user->id,
        'face_descriptors' => fakeFaceDescriptors(),
        'face_registered_at' => now(),
        'face_registered_by' => $staff->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 3)
        ->assertDontSee('Ulangi Foto')
        ->call('retakeFace')
        ->assertForbidden();

    Livewire::test('pages::onboarding.index')
        ->call('storeFaceDescriptors', [array_fill(0, 128, 0.25)])
        ->assertForbidden();

    expect($student->fresh()->face_registered_by)->toBe($staff->id);
});

test('with in-person registration the face step only explains where to go', function () {
    disallowSelfFaceRegistration();

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertSet('step', 2)
        ->assertSee(__('Wajah didaftarkan langsung di sekolah'))
        ->assertDontSee(__('Ambil Foto Wajah'))
        ->call('storeFaceDescriptors', fakeFaceDescriptors())
        ->assertForbidden();

    expect($student->fresh()->hasRegisteredFace())->toBeFalse();
});

test('with in-person registration a student finishes onboarding without a face and can use the app', function () {
    disallowSelfFaceRegistration();

    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('continueWithoutFace')
        ->assertSet('step', 3)
        ->assertDontSee('Ulangi Foto')
        ->call('completeOnboarding')
        ->assertRedirect(route('dashboard'));

    expect($student->fresh()->onboarded_at)->not->toBeNull();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Wajah Anda belum terdaftar'));
});

test('the face step cannot be skipped while self-registration is on', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->nisnVerified()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->call('continueWithoutFace')
        ->assertForbidden();
});

test('a student whose face staff registered in person skips onboarding entirely', function () {
    disallowSelfFaceRegistration();

    $staff = userWithRole(UserRole::WaliKelas);
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id]);

    app(RegisterStudentFace::class)->handle($student, fakeFaceDescriptors(), null, $staff);

    expect($student->fresh())
        ->hasStaffVerifiedFace()->toBeTrue()
        ->needsOnboarding()->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('Wajah Anda belum terdaftar'));
});

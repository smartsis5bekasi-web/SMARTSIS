<?php

use App\Enums\UserRole;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

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
    'too few samples' => [[array_fill(0, 128, 0.5)]],
    'wrong dimension' => [array_fill(0, 3, array_fill(0, 64, 0.5))],
    'non numeric values' => [array_fill(0, 3, array_fill(0, 128, 'x'))],
]);

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

test('onboarding cannot be completed before the face is registered', function () {
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

<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('the topbar falls back to initials when the account has no photo', function () {
    $user = User::factory()->create(['name' => 'Super Admin']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('SA')
        ->assertDontSee('<img src="/storage/', false);
});

test('the topbar shows the photo once the account has one', function () {
    $user = User::factory()->create(['avatar_url' => '/storage/avatars/admin.jpg']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('/storage/avatars/admin.jpg', false);
});

test('a students account photo comes from their student record', function () {
    $user = User::factory()->create();
    Student::factory()->create(['user_id' => $user->id, 'avatar_url' => '/storage/students/foto.jpg']);

    expect($user->avatarUrl())->toBe('/storage/students/foto.jpg');
});

test('uploading a photo writes it back to the student record, not a second copy', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $student->refresh();

    expect($student->avatar_url)->not->toBeNull()
        ->and($user->refresh()->avatar_url)->toBeNull()
        ->and($user->avatarUrl())->toBe($student->avatar_url);

    Storage::disk('public')->assertExists(Str::after($student->avatar_url, '/storage/'));
});

test('an account with no profile record keeps its photo on the user row', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->avatar_url)->not->toBeNull();
});

test('replacing a photo deletes the file it replaces', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('photo', UploadedFile::fake()->image('first.jpg'))
        ->call('updateProfileInformation');

    $firstPath = Str::after($user->refresh()->avatar_url, '/storage/');

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('photo', UploadedFile::fake()->image('second.jpg'))
        ->call('updateProfileInformation');

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists(Str::after($user->refresh()->avatar_url, '/storage/'));
});

test('a photo can be removed again', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->call('updateProfileInformation');

    $path = Str::after($user->refresh()->avatar_url, '/storage/');

    $component->call('removePhoto');

    expect($user->refresh()->avatar_url)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('the photo must be an image within the size limit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('photo', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->call('updateProfileInformation')
        ->assertHasErrors(['photo' => 'image']);
});

test('a teacher can edit their phone and sees their nip read-only', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruMapel->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id, 'nip' => '198001012005011001']);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSet('phone', $teacher->phone)
        ->assertSee('Telepon')
        ->assertSee('Data Kepegawaian')
        ->assertSee('198001012005011001')
        ->assertSee('Guru Mata Pelajaran')
        // Student-only fields stay off a teacher's profile.
        ->assertDontSee('Alamat')
        ->assertDontSee('Tanggal Lahir')
        ->set('name', 'Budi Baru')
        ->set('phone', '08999999999')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($teacher->refresh())
        ->phone->toBe('08999999999')
        // The name is mirrored so master data and the account never disagree.
        ->name->toBe('Budi Baru')
        ->and($user->refresh()->name)->toBe('Budi Baru');
});

test('a student can edit every personal field and sees their class read-only', function () {
    $classroom = Classroom::factory()->create(['name' => 'XI IPA 1']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::Siswa->value);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'classroom_id' => $classroom->id,
        'major_id' => $classroom->major_id,
        'nis' => '2025001',
        'gender' => 'P',
        'address' => 'Alamat Lama',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSet('gender', 'P')
        ->assertSet('birth_date', $student->birth_date->format('Y-m-d'))
        ->assertSet('address', 'Alamat Lama')
        ->assertSee('Jenis Kelamin')
        ->assertSee('Tanggal Lahir')
        ->assertSee('Alamat')
        ->assertSee('Data Sekolah')
        ->assertSee('2025001')
        ->assertSee('XI IPA 1')
        // Students are reached through their guardians, not their own phone.
        ->assertDontSee('Telepon')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('gender', 'L')
        ->set('birth_date', now()->subYears(16)->format('Y-m-d'))
        ->set('address', 'Alamat Baru')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($student->refresh())
        ->gender->toBe('L')
        ->address->toBe('Alamat Baru')
        ->and($student->birth_date->format('Y-m-d'))->toBe(now()->subYears(16)->format('Y-m-d'));
});

test('a student cannot set a birth date outside the accepted age range', function () {
    $user = User::factory()->create();
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('birth_date', now()->subYears(5)->format('Y-m-d'))
        ->call('updateProfileInformation')
        ->assertHasErrors(['birth_date' => 'before_or_equal']);
});

test('a student cannot reach the administrative fields through the form', function () {
    $ownClass = Classroom::factory()->create();
    $otherClass = Classroom::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'classroom_id' => $ownClass->id,
        'nis' => '2025001',
    ]);

    $this->actingAs($user);

    // No property exists for them, so a crafted request has nothing to bind to.
    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($student->refresh())
        ->classroom_id->toBe($ownClass->id)
        ->nis->toBe('2025001')
        ->and($otherClass->students()->count())->toBe(0);
});

test('a parent can edit their phone and sees their children read-only', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::OrangTua->value);
    $parent = ParentGuardian::factory()->create(['user_id' => $user->id]);
    $child = Student::factory()->create(['name' => 'Anak Demo']);
    $parent->students()->attach($child->id, ['relationship' => 'Ayah']);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSet('phone', $parent->phone)
        ->assertSee('Telepon')
        ->assertSee('Data Akun')
        ->assertSee('Anak Demo')
        ->assertDontSee('Tanggal Lahir')
        ->set('name', 'Bapak Baru')
        ->set('phone', '08111111111')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($parent->refresh())
        ->phone->toBe('08111111111')
        ->name->toBe('Bapak Baru');
});

test('a parent without a photo of their own keeps it on the user row', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    ParentGuardian::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    // The parents table carries no avatar column, so the account keeps it.
    expect($user->refresh()->avatar_url)->not->toBeNull()
        ->and($user->avatarUrl())->toBe($user->avatar_url);
});

test('an account with no profile record sees only the shared fields', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::settings.profile')
        ->assertSee('Foto Profil')
        ->assertSee('Name')
        ->assertSee('Email')
        ->assertDontSee('Data Sekolah')
        ->assertDontSee('Data Kepegawaian')
        ->assertDontSee('Data Akun')
        ->assertDontSee('Telepon')
        ->assertDontSee('Alamat')
        ->assertDontSee('Jenis Kelamin');
});

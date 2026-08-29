<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());
});

test('creating a guru provisions a user, role, and teacher profile', function () {
    Livewire::test('pages::master-data.teachers.create')
        ->set('name', 'Pak Budi')
        ->set('nip', '198001012005011001')
        ->set('phone', '08123456789')
        ->set('role', UserRole::GuruMapel->value)
        ->set('email', 'budi@smartsis.test')
        ->set('password', 'rahasia123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.teachers'));

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
    Livewire::test('pages::master-data.teachers.create')
        ->set('name', 'Pak Budi')
        ->set('role', UserRole::GuruMapel->value)
        ->call('save')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);
});

test('the role must be a valid teacher role', function () {
    Livewire::test('pages::master-data.teachers.create')
        ->set('name', 'Pak Budi')
        ->set('email', 'budi@smartsis.test')
        ->set('password', 'rahasia123')
        ->set('role', UserRole::Siswa->value)
        ->call('save')
        ->assertHasErrors('role');
});

test('the email must be unique across users', function () {
    User::factory()->create(['email' => 'taken@smartsis.test']);

    Livewire::test('pages::master-data.teachers.create')
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

    Livewire::test('pages::master-data.teachers.edit', ['teacher' => $teacher])
        ->assertSet('name', 'Lama')
        ->assertSet('role', UserRole::GuruMapel->value)
        ->set('name', 'Baru')
        ->set('role', UserRole::WaliKelas->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.teachers'));

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

    Livewire::test('pages::master-data.teachers.edit', ['teacher' => $teacher])
        ->set('password', 'passwordbaru')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('passwordbaru', $user->refresh()->password))->toBeTrue();
});

test('export downloads an xlsx of the guru data', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruMapel->value);
    Teacher::factory()->create(['user_id' => $user->id, 'name' => 'Pak Budi']);

    Livewire::test('pages::master-data.teachers')
        ->call('export')
        ->assertFileDownloaded('data-guru-'.now()->format('Y-m-d').'.xlsx');
});

test('the import template can be downloaded from the modal', function () {
    Livewire::test('pages::master-data.teachers')
        ->call('downloadTemplate')
        ->assertFileDownloaded('template-import-guru.xlsx');
});

test('importing a valid xlsx provisions users, roles, and teacher profiles', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['nama', 'nip', 'email', 'telepon', 'peran', 'password'],
        ['Budi Santoso', '198001012005011001', 'budi@smartsis.test', '081234567890', 'guru_mapel', 'rahasia123'],
    ]);
    $path = sys_get_temp_dir().'/guru-import-test.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    Livewire::test('pages::master-data.teachers')
        ->set('importFile', UploadedFile::fake()->createWithContent('guru.xlsx', (string) file_get_contents($path)))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importErrors', []);

    $budi = User::where('email', 'budi@smartsis.test')->first();

    expect($budi)->not->toBeNull()
        ->and($budi->hasRole(UserRole::GuruMapel->value))->toBeTrue();

    $this->assertDatabaseHas('teachers', ['user_id' => $budi->id, 'name' => 'Budi Santoso']);
});

test('importing a valid csv provisions users, roles, and teacher profiles', function () {
    $csv = implode("\n", [
        'nama;nip;email;telepon;peran;password',
        'Budi Santoso;198001012005011001;budi@smartsis.test;081234567890;guru_mapel;rahasia123',
        'Siti Aminah;;siti@smartsis.test;;Wali Kelas;',
    ]);

    Livewire::test('pages::master-data.teachers')
        ->call('openImportModal')
        ->set('importFile', UploadedFile::fake()->createWithContent('guru.csv', $csv))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('showImportModal', false)
        ->assertSet('importErrors', []);

    $budi = User::where('email', 'budi@smartsis.test')->first();
    $siti = User::where('email', 'siti@smartsis.test')->first();

    expect($budi)->not->toBeNull()
        ->and($budi->hasRole(UserRole::GuruMapel->value))->toBeTrue()
        ->and(Hash::check('rahasia123', $budi->password))->toBeTrue()
        ->and($siti)->not->toBeNull()
        ->and($siti->hasRole(UserRole::WaliKelas->value))->toBeTrue()
        ->and(Hash::check('password', $siti->password))->toBeTrue();

    $this->assertDatabaseHas('teachers', [
        'user_id' => $budi->id,
        'name' => 'Budi Santoso',
        'nip' => '198001012005011001',
        'phone' => '081234567890',
    ]);
    $this->assertDatabaseHas('teachers', ['user_id' => $siti->id, 'name' => 'Siti Aminah', 'nip' => null]);
});

test('import rejects invalid rows and creates nothing', function () {
    User::factory()->create(['email' => 'taken@smartsis.test']);

    $csv = implode("\n", [
        'nama,nip,email,telepon,peran,password',
        'Valid Guru,,valid@smartsis.test,,guru_mapel,',
        'Dupe Email,,taken@smartsis.test,,guru_mapel,',
        'Bad Role,,badrole@smartsis.test,,siswa,',
    ]);

    $component = Livewire::test('pages::master-data.teachers')
        ->set('importFile', UploadedFile::fake()->createWithContent('guru.csv', $csv))
        ->call('import');

    expect($component->get('importErrors'))->toHaveCount(2);

    $this->assertDatabaseMissing('users', ['email' => 'valid@smartsis.test']);
    $this->assertDatabaseMissing('teachers', ['name' => 'Valid Guru']);
});

test('import rejects rows that duplicate each other inside the file', function () {
    $csv = implode("\n", [
        'nama;nip;email;telepon;peran;password',
        'Guru Satu;123;satu@smartsis.test;;guru_mapel;',
        'Guru Dua;123;satu@smartsis.test;;guru_piket;',
    ]);

    $component = Livewire::test('pages::master-data.teachers')
        ->set('importFile', UploadedFile::fake()->createWithContent('guru.csv', $csv))
        ->call('import');

    expect($component->get('importErrors'))->toHaveCount(1);

    $this->assertDatabaseMissing('users', ['email' => 'satu@smartsis.test']);
});

test('import fails when the file does not match the template columns', function () {
    $csv = implode("\n", [
        'nama;nip',
        'Budi;123',
    ]);

    $component = Livewire::test('pages::master-data.teachers')
        ->set('importFile', UploadedFile::fake()->createWithContent('guru.csv', $csv))
        ->call('import');

    expect($component->get('importErrors'))->toHaveCount(1)
        ->and($component->get('importErrors')[0])->toContain('email');

    $this->assertDatabaseCount('teachers', 0);
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

test('the guru list shows the newest entries first', function () {
    $older = Teacher::factory()->create(['name' => 'Zulkifli Lama', 'created_at' => now()->subDay()]);
    $newer = Teacher::factory()->create(['name' => 'Andi Baru', 'created_at' => now()]);

    Livewire::test('pages::master-data.teachers')
        ->assertSeeInOrder([$newer->name, $older->name]);
});

test('editing a guru can upload a photo and replaces the previous file', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruMapel->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    Livewire::test('pages::master-data.teachers.edit', ['teacher' => $teacher])
        ->set('avatar', UploadedFile::fake()->image('pertama.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $firstPath = Str::after($teacher->refresh()->avatar_url, '/storage/');
    Storage::disk('public')->assertExists($firstPath);

    Livewire::test('pages::master-data.teachers.edit', ['teacher' => $teacher])
        ->set('avatar', UploadedFile::fake()->image('kedua.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists(Str::after($teacher->refresh()->avatar_url, '/storage/'));
});

test('editing a guru without uploading keeps the existing photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruMapel->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id, 'avatar_url' => '/storage/teachers/lama.jpg']);

    Livewire::test('pages::master-data.teachers.edit', ['teacher' => $teacher])
        ->set('name', 'Nama Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($teacher->refresh())
        ->name->toBe('Nama Baru')
        ->avatar_url->toBe('/storage/teachers/lama.jpg');
});

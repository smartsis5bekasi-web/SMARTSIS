<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Major;
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

/**
 * Fields the create form requires on every submission, on top of whatever the
 * test under it is actually asserting.
 *
 * @return array<string, string>
 */
function requiredStudentInput(): array
{
    return [
        'email' => fake()->unique()->safeEmail(),
        'password' => 'rahasia123',
        'birth_date' => now()->subYears(16)->format('Y-m-d'),
    ];
}

test('creating a student stores the record', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set(requiredStudentInput())
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
        ->set(requiredStudentInput())
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
        ->set(requiredStudentInput())
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

test('creating a student with new parents gives each of them a login account', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set(requiredStudentInput())
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->call('addParent')
        ->set('parents.0.name', 'Budi Hartono')
        ->set('parents.0.email', 'budi.hartono@example.test')
        ->set('parents.0.phone', '081234567890')
        ->set('parents.0.relationship', 'Ayah')
        ->call('addParent')
        ->set('parents.1.name', 'Siti Aminah')
        ->set('parents.1.email', 'siti.aminah@example.test')
        ->set('parents.1.phone', '081234567891')
        ->set('parents.1.relationship', 'Ibu')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::firstWhere('nis', '0012345678');
    $ayah = $student->parents->firstWhere('name', 'Budi Hartono');

    expect($student->parents)->toHaveCount(2)
        ->and($ayah->pivot->relationship)->toBe('Ayah')
        ->and($ayah->phone)->toBe('081234567890')
        ->and($student->parents->firstWhere('name', 'Siti Aminah')->pivot->relationship)->toBe('Ibu');

    // Every parent added through the form is reachable: an account with the
    // Orang Tua role, so they can sign in and follow their child.
    expect($ayah->user)->not->toBeNull()
        ->and($ayah->user->email)->toBe('budi.hartono@example.test')
        ->and($ayah->user->hasRole(UserRole::OrangTua->value))->toBeTrue();
});

test('a new parent row requires an email and a phone number', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set(requiredStudentInput())
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->call('addParent')
        ->set('parents.0.name', 'Budi Hartono')
        ->call('save')
        ->assertHasErrors([
            'parents.0.email' => 'required',
            'parents.0.phone' => 'required',
        ]);
});

test('a parent row requires a name', function () {
    $classroom = Classroom::factory()->create();

    Livewire::test('pages::master-data.students.create')
        ->set(requiredStudentInput())
        ->set('name', 'Hafidz')
        ->set('nis', '0012345678')
        ->set('classroom_id', $classroom->id)
        ->call('addParent')
        ->call('save')
        ->assertHasErrors(['parents.0.name' => 'required']);
});

test('editing preloads the linked parents and updates the relationship', function () {
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);
    $parent = ParentGuardian::factory()->withoutAccount()->create(['name' => 'Budi Hartono']);
    $student->parents()->attach($parent->id, ['relationship' => 'Ayah']);

    // The parent record itself is owned by master data > orang tua; this form
    // only decides who is linked to the student and as what.
    Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->assertSet('parents.0.parent_id', $parent->id)
        ->assertSet('parents.0.name', 'Budi Hartono')
        ->assertSet('parents.0.relationship', 'Ayah')
        ->set('parents.0.relationship', 'Wali')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->parents()->first())
        ->name->toBe('Budi Hartono')
        ->pivot->relationship->toBe('Wali');
});

test('editing links an existing parent found through the search box', function () {
    $classroom = Classroom::factory()->create();
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);
    $parent = ParentGuardian::factory()->withoutAccount()->create([
        'name' => 'Siti Aminah',
        'phone' => '081234567890',
    ]);

    $component = Livewire::test('pages::master-data.students.edit', ['student' => $student])
        ->call('addParent')
        ->set('parents.0.search', 'Siti');

    expect($component->instance()->parentSearchResults(0)->pluck('id'))->toContain($parent->id);

    $component->call('selectParent', 0, $parent->id)
        ->assertSet('parents.0.parent_id', $parent->id)
        ->assertSet('parents.0.name', 'Siti Aminah')
        ->set('parents.0.relationship', 'Ibu')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->parents()->count())->toBe(1)
        ->and($student->parents()->first()->pivot->relationship)->toBe('Ibu');

    // Linking must reuse the record, never duplicate it.
    expect(ParentGuardian::where('name', 'Siti Aminah')->count())->toBe(1);
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

test('export downloads an xlsx of the siswa data', function () {
    Student::factory()->create(['name' => 'Ahmad']);

    Livewire::test('pages::master-data.students.index')
        ->call('export')
        ->assertFileDownloaded('data-siswa-'.now()->format('Y-m-d').'.xlsx');
});

test('the siswa import template can be downloaded from the modal', function () {
    Livewire::test('pages::master-data.students.index')
        ->call('downloadTemplate')
        ->assertFileDownloaded('template-import-siswa.xlsx');
});

test('importing a valid csv creates students with relations and parents', function () {
    $classroom = Classroom::factory()->create(['name' => 'X IPA 1']);
    $major = Major::factory()->create(['name' => 'IPA']);

    $csv = implode("\n", [
        'nama;nis;nisn;email;jenis_kelamin;tanggal_lahir;alamat;kelas;jurusan;orang_tua;hubungan;telepon_orang_tua',
        'Ahmad Fauzi;2024001;0051234567;ahmad.fauzi@example.test;L;2008-03-15;Jl. Merdeka No. 1;X IPA 1;IPA;Budi Fauzi;Ayah;081234567890',
        'Dewi Lestari;2024002;;dewi.lestari@example.test;Perempuan;;;x ipa 1;;;;',
    ]);

    Livewire::test('pages::master-data.students.index')
        ->call('openImportModal')
        ->set('importFile', UploadedFile::fake()->createWithContent('siswa.csv', $csv))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('showImportModal', false)
        ->assertSet('importErrors', []);

    $this->assertDatabaseHas('students', [
        'name' => 'Ahmad Fauzi',
        'nis' => '2024001',
        'nisn' => '0051234567',
        'gender' => 'L',
        'classroom_id' => $classroom->id,
        'major_id' => $major->id,
    ]);
    $this->assertDatabaseHas('students', [
        'name' => 'Dewi Lestari',
        'nis' => '2024002',
        'gender' => 'P',
        'classroom_id' => $classroom->id,
        'major_id' => null,
    ]);

    $ahmad = Student::where('nis', '2024001')->first();
    $parent = $ahmad->parents()->first();

    // Every imported row carries an email, so each student lands with a login
    // account already attached.
    expect($ahmad->user)->not->toBeNull()
        ->and($ahmad->user->email)->toBe('ahmad.fauzi@example.test')
        ->and($ahmad->user->hasRole(UserRole::Siswa->value))->toBeTrue();

    expect($parent)->not->toBeNull()
        ->and($parent->name)->toBe('Budi Fauzi')
        ->and($parent->pivot->relationship)->toBe('Ayah')
        ->and($parent->phone)->toBe('081234567890');

    expect(Student::where('nis', '2024002')->first()->parents()->count())->toBe(0);
});

test('siswa import rejects unknown kelas and duplicate nis, creating nothing', function () {
    Classroom::factory()->create(['name' => 'X IPA 1']);
    Student::factory()->create(['nis' => '2024001']);

    $csv = implode("\n", [
        'nama;nis;nisn;email;jenis_kelamin;tanggal_lahir;alamat;kelas;jurusan;orang_tua;hubungan;telepon_orang_tua',
        'Valid Siswa;2024009;;valid.siswa@example.test;;;;X IPA 1;;;;',
        'Dupe Nis;2024001;;dupe.nis@example.test;;;;X IPA 1;;;;',
        'Kelas Salah;2024010;;kelas.salah@example.test;;;;XII Tidak Ada;;;;',
    ]);

    $component = Livewire::test('pages::master-data.students.index')
        ->set('importFile', UploadedFile::fake()->createWithContent('siswa.csv', $csv))
        ->call('import');

    expect($component->get('importErrors'))->toHaveCount(2);

    $this->assertDatabaseMissing('students', ['nis' => '2024009']);
    $this->assertDatabaseMissing('students', ['nis' => '2024010']);
});

test('the siswa list shows the newest entries first', function () {
    $older = Student::factory()->create(['name' => 'Zulkifli Lama', 'created_at' => now()->subDay()]);
    $newer = Student::factory()->create(['name' => 'Andi Baru', 'created_at' => now()]);

    Livewire::test('pages::master-data.students.index')
        ->assertSeeInOrder([$newer->name, $older->name]);
});

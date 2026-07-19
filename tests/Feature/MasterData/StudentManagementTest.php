<?php

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
        'nama;nis;nisn;jenis_kelamin;tanggal_lahir;alamat;kelas;jurusan;orang_tua;hubungan;telepon_orang_tua',
        'Ahmad Fauzi;2024001;0051234567;L;2008-03-15;Jl. Merdeka No. 1;X IPA 1;IPA;Budi Fauzi;Ayah;081234567890',
        'Dewi Lestari;2024002;;Perempuan;;;x ipa 1;;;;',
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
        'nama;nis;nisn;jenis_kelamin;tanggal_lahir;alamat;kelas;jurusan;orang_tua;hubungan;telepon_orang_tua',
        'Valid Siswa;2024009;;;;;X IPA 1;;;;',
        'Dupe Nis;2024001;;;;;X IPA 1;;;;',
        'Kelas Salah;2024010;;;;;XII Tidak Ada;;;;',
    ]);

    $component = Livewire::test('pages::master-data.students.index')
        ->set('importFile', UploadedFile::fake()->createWithContent('siswa.csv', $csv))
        ->call('import');

    expect($component->get('importErrors'))->toHaveCount(2);

    $this->assertDatabaseMissing('students', ['nis' => '2024009']);
    $this->assertDatabaseMissing('students', ['nis' => '2024010']);
});

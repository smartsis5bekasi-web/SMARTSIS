<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('public');
});

/**
 * A signed-in siswa with a linked student record, which is what unlocks the
 * self-service absensi panel.
 */
function signedInSiswa(): Student
{
    $siswa = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $siswa->id]);

    test()->actingAs($siswa);

    return $student;
}

/** A 1×1 PNG, the smallest payload the selfie decoder will accept. */
function selfieDataUrl(): string
{
    return 'data:image/png;base64,'.base64_encode(base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
}

test('the camera capture stores the selfie and the gps fix on the check-in', function () {
    $student = signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')->call('record', [
        'photo' => selfieDataUrl(),
        'latitude' => -6.260733,
        'longitude' => 106.78104,
        'accuracy' => 11.4,
    ]);

    $attendance = $student->attendances()->sole();

    expect($attendance->check_in_photo_path)->toContain('/storage/attendances/')
        ->and($attendance->check_in_latitude)->toEqualWithDelta(-6.260733, 0.0000001)
        ->and($attendance->check_in_longitude)->toEqualWithDelta(106.78104, 0.0000001)
        ->and($attendance->check_in_accuracy)->toBe(11)
        ->and($attendance->location('in'))->not->toBeNull()
        // The location is what verifies a self-service absensi now that no
        // face is matched.
        ->and($attendance->isVerified())->toBeTrue();

    expect(Storage::disk('public')->allFiles('attendances'))->toHaveCount(1);
});

test('a denied camera or location permission still records the attendance', function () {
    $student = signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')->call('record', [
        'photo' => null,
        'latitude' => null,
        'longitude' => null,
        'accuracy' => null,
    ]);

    $attendance = $student->attendances()->sole();

    expect($attendance->checked_in_at)->not->toBeNull()
        ->and($attendance->check_in_photo_path)->toBeNull()
        ->and($attendance->hasLocation())->toBeFalse()
        // Recorded, but left for a Guru Piket to confirm: nothing places the
        // student anywhere.
        ->and($attendance->isVerified())->toBeFalse();
});

test('a check-out that finally carries a fix verifies a check-in that did not', function () {
    $student = signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')->call('record', []);

    expect($student->attendances()->sole()->isVerified())->toBeFalse();

    $this->travelTo(now()->setTime(15, 30));

    Livewire::test('pages::attendance.absensi.index')->call('record', [
        'latitude' => -6.260733,
        'longitude' => 106.78104,
        'accuracy' => 9,
    ]);

    $attendance = $student->attendances()->sole();

    expect($attendance->checked_out_at)->not->toBeNull()
        ->and($attendance->isVerified())->toBeTrue()
        ->and($attendance->location('out'))->not->toBeNull();
});

test('a tampered photo payload is discarded instead of stored', function () {
    $student = signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')->call('record', [
        'photo' => 'data:image/png;base64,'.base64_encode('<?php echo "not an image";'),
        'latitude' => 999,
        'longitude' => 999,
    ]);

    $attendance = $student->attendances()->sole();

    expect($attendance->check_in_photo_path)->toBeNull()
        ->and($attendance->check_in_latitude)->toBeNull()
        ->and($attendance->isVerified())->toBeFalse()
        ->and(Storage::disk('public')->allFiles('attendances'))->toBeEmpty();
});

test('the sakit shortcut records today as sakit with its uploaded proof, pending verification', function () {
    $student = signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')
        ->call('openDeclaration', 'sakit')
        ->assertSet('declaring', 'sakit')
        ->set('declareAttachment', UploadedFile::fake()->image('surat-dokter.jpg'))
        ->call('submitDeclaration')
        ->assertHasNoErrors()
        ->assertSet('declaring', '');

    $attendance = $student->attendances()->sole();

    expect($attendance->status)->toBe(AttendanceStatus::Sakit)
        ->and($attendance->method)->toBe('self')
        ->and($attendance->attachment_path)->toContain('/storage/attendances/proofs/')
        ->and($attendance->checked_in_at)->toBeNull()
        ->and($attendance->isVerified())->toBeFalse()
        ->and($attendance->needsCheckOut())->toBeFalse();
});

test('the izin shortcut requires both a proof and a reason', function () {
    signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')
        ->call('openDeclaration', 'izin')
        ->call('submitDeclaration')
        ->assertHasErrors(['declareAttachment' => 'required', 'declareReason' => 'required']);

    expect(Attendance::query()->count())->toBe(0);
});

test('the izin shortcut stores the reason it was given', function () {
    $student = signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')
        ->call('openDeclaration', 'izin')
        ->set('declareAttachment', UploadedFile::fake()->create('surat-izin.pdf', 200, 'application/pdf'))
        ->set('declareReason', 'Menghadiri pernikahan keluarga di luar kota.')
        ->call('submitDeclaration')
        ->assertHasNoErrors();

    $attendance = $student->attendances()->sole();

    expect($attendance->status)->toBe(AttendanceStatus::Izin)
        ->and($attendance->reason)->toBe('Menghadiri pernikahan keluarga di luar kota.')
        // A PDF surat is linked rather than previewed as a photo.
        ->and($attendance->imageAttachmentUrl())->toBeNull();
});

test('an attachment over 1 MB is rejected', function () {
    signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')
        ->call('openDeclaration', 'sakit')
        ->set('declareAttachment', UploadedFile::fake()->create('surat.pdf', 2048, 'application/pdf'))
        ->call('submitDeclaration')
        ->assertHasErrors(['declareAttachment' => 'max']);

    expect(Attendance::query()->count())->toBe(0);
});

test('a status the student may not declare is rejected outright', function () {
    signedInSiswa();

    Livewire::test('pages::attendance.absensi.index')
        ->call('openDeclaration', 'hadir')
        ->assertStatus(400);
});

test('a second declaration on a day that is already recorded is refused', function () {
    $student = signedInSiswa();

    Attendance::factory()->for($student)->create(['date' => now()->toDateString()]);

    Livewire::test('pages::attendance.absensi.index')
        ->call('openDeclaration', 'sakit')
        ->set('declareAttachment', UploadedFile::fake()->image('surat.jpg'))
        ->call('submitDeclaration');

    expect($student->attendances()->count())->toBe(1)
        ->and($student->attendances()->sole()->status)->toBe(AttendanceStatus::Hadir);
});

test('a sakit day closes the scanner: absensi pulang is never unlocked', function () {
    $student = signedInSiswa();

    Attendance::factory()->for($student)->sakit()->unverified()->create(['date' => now()->toDateString()]);

    $component = Livewire::test('pages::attendance.absensi.index');

    expect($component->instance()->nextScanStep())->toBeNull()
        ->and($component->instance()->isScannerOpen())->toBeFalse();

    // Even a forced call cannot check out a day that never checked in.
    $component->call('record', []);

    expect($component->get('lastResult')['ok'])->toBeFalse()
        ->and($student->attendances()->sole()->checked_out_at)->toBeNull();
});

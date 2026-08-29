<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\ParentGuardian;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('roles with attendance view access can open the monitoring page', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('attendance.absensi'))
        ->assertOk();
})->with([
    'kepala sekolah' => [UserRole::KepalaSekolah],
    'wakasek kesiswaan' => [UserRole::WakasekKesiswaan],
    'guru bk' => [UserRole::GuruBk],
    'wali kelas' => [UserRole::WaliKelas],
    'guru piket' => [UserRole::GuruPiket],
    'guru mapel' => [UserRole::GuruMapel],
]);

test('the recap page is available to attendance viewers', function () {
    $this->actingAs(userWithRole(UserRole::WaliKelas))
        ->get(route('attendance.absensi.recap'))
        ->assertOk();
});

test('only attendance managers keep the scan kiosk; a siswa is sent to the unified absensi page', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('attendance.absensi.scan'))
        ->assertOk();

    $this->actingAs(userWithRole(UserRole::KepalaSekolah))
        ->get(route('attendance.absensi.scan'))
        ->assertForbidden();

    $siswa = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $siswa->id]);

    $this->actingAs($siswa)
        ->get(route('attendance.absensi.scan'))
        ->assertRedirect(route('attendance.absensi'));
});

test('a siswa self-scanning only ever sees and records their own face template', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $own = Student::factory()->onboarded()->create(['user_id' => $siswa->id]);
    $other = Student::factory()->onboarded()->create();

    $this->actingAs($siswa);

    // The templates now come from their own endpoint rather than the page
    // markup, so that is where the 1:1 scoping has to hold.
    $templates = $this->getJson(route('attendance.absensi.face-templates'))
        ->assertOk()
        ->json();

    expect($templates)->toHaveCount(1)
        ->and($templates[0]['id'])->toBe($own->id);

    // Even if the browser payload is tampered with to pass another
    // student's id, the server pins the recorded student to the siswa's own
    // linked record.
    Livewire::test('pages::attendance.absensi.index')->call('record', $other->id);

    expect($own->attendances()->count())->toBe(1)
        ->and($other->attendances()->count())->toBe(0);
});

test('a siswa cannot record absensi pulang before absensi masuk', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $siswa->id]);

    $this->actingAs($siswa);

    $component = Livewire::test('pages::attendance.absensi.index');

    // Before check-in the only available step is masuk — the first scan
    // records check-in, never check-out.
    $component->call('record', $student->id);

    expect($component->get('lastResult')['ok'])->toBeTrue();

    $attendance = $student->attendances()->first();

    expect($attendance->checked_in_at)->not->toBeNull()
        ->and($attendance->checked_out_at)->toBeNull();
});

test('only attendance managers can open the settings page', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('attendance.absensi.settings'))
        ->assertOk();

    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('attendance.absensi.settings'))
        ->assertForbidden();
});

test('a siswa sees their personal attendance history', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $siswa->id]);
    Attendance::factory()->for($student)->late()->create();

    $this->actingAs($siswa)
        ->get(route('attendance.absensi'))
        ->assertOk()
        ->assertSee('riwayat kehadiran Anda')
        ->assertSee('Terlambat');
});

test('an orang tua sees their child attendance history', function () {
    $ortu = userWithRole(UserRole::OrangTua);
    $parent = ParentGuardian::factory()->create(['user_id' => $ortu->id]);
    $student = Student::factory()->create();
    $parent->students()->attach($student->id, ['relationship' => 'Ayah']);

    Attendance::factory()->for($student)->sakit()->create();

    $this->actingAs($ortu)
        ->get(route('attendance.absensi'))
        ->assertOk()
        ->assertSee('Sakit');
});

test('non-managers cannot change a status from the monitoring page', function () {
    $student = Student::factory()->create();

    $this->actingAs(userWithRole(UserRole::KepalaSekolah));

    Livewire::test('pages::attendance.absensi.index')
        ->call('markStatus', $student->id, 'izin')
        ->assertStatus(403);
});

test('a manager can mark a status from the monitoring page', function () {
    $student = Student::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.index')
        ->call('markStatus', $student->id, 'izin')
        ->assertOk();

    expect($student->attendances()->count())->toBe(1);
});

test('the kiosk records a check-in and reports duplicates', function () {
    $student = Student::factory()->onboarded()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::attendance.absensi.scan')
        ->call('setMode', 'masuk')
        ->call('record', $student->id);

    expect($component->get('lastResult')['ok'])->toBeTrue()
        ->and($student->attendances()->count())->toBe(1);

    $component->call('record', $student->id);

    expect($component->get('lastResult')['ok'])->toBeFalse()
        ->and($student->attendances()->count())->toBe(1);
});

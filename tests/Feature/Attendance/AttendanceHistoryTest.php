<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the riwayat page is available to attendance viewers', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('attendance.absensi.history'))
        ->assertOk()
        ->assertSee('Riwayat Absen');
});

test('a siswa only ever sees their own riwayat', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $own = Student::factory()->onboarded()->create(['user_id' => $siswa->id, 'name' => 'Siswa Sendiri']);
    $other = Student::factory()->create(['name' => 'Siswa Lain']);

    Attendance::factory()->for($own)->captured()->create();
    Attendance::factory()->for($other)->captured()->create();

    $this->actingAs($siswa)
        ->get(route('attendance.absensi.history'))
        ->assertOk()
        ->assertSee('Siswa Sendiri')
        ->assertDontSee('Siswa Lain');
});

test('an orang tua sees the riwayat of their own children only', function () {
    $ortu = userWithRole(UserRole::OrangTua);
    $parent = ParentGuardian::factory()->create(['user_id' => $ortu->id]);
    $child = Student::factory()->create(['name' => 'Anak Saya']);
    $stranger = Student::factory()->create(['name' => 'Anak Orang']);
    $parent->students()->attach($child->id, ['relationship' => 'Ayah']);

    Attendance::factory()->for($child)->create();
    Attendance::factory()->for($stranger)->create();

    $this->actingAs($ortu)
        ->get(route('attendance.absensi.history'))
        ->assertOk()
        ->assertSee('Anak Saya')
        ->assertDontSee('Anak Orang');
});

test('a wali kelas sees the riwayat of their homeroom only', function () {
    $wali = userWithRole(UserRole::WaliKelas);
    $teacher = Teacher::factory()->create(['user_id' => $wali->id]);
    $homeroom = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);

    $mine = Student::factory()->create(['classroom_id' => $homeroom->id, 'name' => 'Murid Perwalian']);
    $theirs = Student::factory()->create(['name' => 'Murid Kelas Lain']);

    Attendance::factory()->for($mine)->create();
    Attendance::factory()->for($theirs)->create();

    $this->actingAs($wali)
        ->get(route('attendance.absensi.history'))
        ->assertOk()
        ->assertSee('Murid Perwalian')
        ->assertDontSee('Murid Kelas Lain');
});

test('the position of a record outside the reader scope is never opened', function () {
    $siswa = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $siswa->id]);

    $stranger = Attendance::factory()->for(Student::factory())->captured()->create();

    $this->actingAs($siswa);

    $component = Livewire::test('pages::attendance.absensi.history')
        ->call('showLocation', $stranger->id);

    expect($component->instance()->locationAttendance)->toBeNull();
});

test('the map modal opens on the leg that actually carries a fix', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $siswa->id]);

    $attendance = Attendance::factory()->for($student)->create([
        'check_out_latitude' => -6.2607330,
        'check_out_longitude' => 106.7810400,
        'check_out_accuracy' => 8,
        'checked_out_at' => now()->setTime(15, 5),
    ]);

    $this->actingAs($siswa);

    Livewire::test('pages::attendance.absensi.history')
        ->call('showLocation', $attendance->id)
        ->assertSet('locationMoment', 'out')
        ->assertSee('Lokasi Absen')
        ->assertSee('106.781040');
});

test('only a manager can verify a self-declared sakit or izin', function () {
    $student = Student::factory()->create();
    $attendance = Attendance::factory()->for($student)->sakit()->unverified()->create();

    $this->actingAs(userWithRole(UserRole::GuruMapel));

    Livewire::test('pages::attendance.absensi.history')
        ->call('verify', $attendance->id)
        ->assertStatus(403);

    expect($attendance->fresh()->isVerified())->toBeFalse();

    $piket = userWithRole(UserRole::GuruPiket);
    $this->actingAs($piket);

    Livewire::test('pages::attendance.absensi.history')
        ->call('verify', $attendance->id)
        ->assertOk();

    expect($attendance->fresh()->isVerified())->toBeTrue()
        ->and($attendance->fresh()->verified_by)->toBe($piket->id);
});

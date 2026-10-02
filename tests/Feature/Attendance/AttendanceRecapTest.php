<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Exports\AttendanceRecapExport;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\AttendanceRecap;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Give the student one late record on each of the given days of May 2026.
 *
 * @param  array<int, int>  $days
 */
function lateOn(Student $student, array $days): void
{
    foreach ($days as $day) {
        Attendance::factory()->for($student)->late()->create(['date' => "2026-05-{$day}"]);
    }
}

test('the rekap can be narrowed to students late a minimum number of times', function () {
    $often = Student::factory()->create(['name' => 'Sering Telat']);
    $twice = Student::factory()->create(['name' => 'Dua Kali Telat']);
    $once = Student::factory()->create(['name' => 'Sekali Telat']);

    lateOn($often, [4, 5, 6]);
    lateOn($twice, [4, 5]);
    lateOn($once, [4]);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.recap')
        ->set('from', '2026-05-01')
        ->set('to', '2026-05-31')
        ->set('thresholdStatus', AttendanceStatus::Terlambat->value)
        ->set('minCount', 3)
        ->assertSee('Sering Telat')
        ->assertDontSee('Dua Kali Telat')
        ->assertDontSee('Sekali Telat')
        ->call('applyPreset', AttendanceStatus::Terlambat->value, 2)
        ->assertSee('Sering Telat')
        ->assertSee('Dua Kali Telat')
        ->assertDontSee('Sekali Telat')
        ->call('clearThreshold')
        ->assertSee('Sekali Telat');
});

test('only records inside the period count toward the minimum', function () {
    $student = Student::factory()->create(['name' => 'Telat Bulan Lalu']);
    lateOn($student, [4, 5, 6]);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.recap')
        ->set('from', '2026-06-01')
        ->set('to', '2026-06-30')
        ->set('thresholdStatus', AttendanceStatus::Terlambat->value)
        ->set('minCount', 1)
        ->assertDontSee('Telat Bulan Lalu');
});

test('the rekap export contains exactly the filtered rows', function () {
    $classroom = Classroom::factory()->create(['name' => 'XI IPA 2']);
    $often = Student::factory()->create(['name' => 'Sering Telat', 'nis' => '5550001', 'classroom_id' => $classroom->id]);
    $rare = Student::factory()->create(['name' => 'Jarang Telat', 'nis' => '5550002', 'classroom_id' => $classroom->id]);

    lateOn($often, [4, 5, 6]);
    lateOn($rare, [4]);
    Attendance::factory()->for($often)->create(['date' => '2026-05-07']);

    $recap = new AttendanceRecap(
        from: Carbon::parse('2026-05-01'),
        to: Carbon::parse('2026-05-31'),
        thresholdStatus: AttendanceStatus::Terlambat,
        minCount: 3,
    );
    $export = new AttendanceRecapExport(Student::query(), $recap);
    $rows = $export->collection();

    expect($rows->pluck('nis')->all())->toBe(['5550001'])
        ->and($export->headings())->toBe(['nama', 'nis', 'kelas', 'hadir', 'terlambat', 'izin', 'sakit', 'alpha', 'menunggu_konfirmasi', 'kehadiran_persen'])
        ->and($export->map($rows->first()))->toBe(['Sering Telat', '5550001', 'XI IPA 2', 1, 3, 0, 0, 0, 0, '100%'])
        ->and($recap->describe())->toContain('Terlambat minimal 3 kali');
});

test('staff can download the filtered rekap', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.recap')
        ->set('from', '2026-05-01')
        ->set('to', '2026-05-31')
        ->set('thresholdStatus', AttendanceStatus::Terlambat->value)
        ->call('exportExcel')
        ->assertFileDownloaded('rekap-absensi-20260501-20260531.xlsx');
});

test('the rekap export stays inside the wali kelas homeroom', function () {
    $wali = userWithRole(UserRole::WaliKelas);
    $teacher = Teacher::factory()->create(['user_id' => $wali->id]);
    $homeroom = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);
    $mine = Student::factory()->create(['classroom_id' => $homeroom->id, 'name' => 'Murid Perwalian']);
    $theirs = Student::factory()->create(['name' => 'Murid Kelas Lain']);

    lateOn($mine, [4, 5]);
    lateOn($theirs, [4, 5]);

    $this->actingAs($wali);

    Livewire::test('pages::attendance.absensi.recap')
        ->set('from', '2026-05-01')
        ->set('to', '2026-05-31')
        ->set('thresholdStatus', AttendanceStatus::Terlambat->value)
        ->set('minCount', 2)
        ->assertSee('Murid Perwalian')
        ->assertDontSee('Murid Kelas Lain');
});

test('a siswa cannot export the rekap', function () {
    $siswa = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $siswa->id]);

    $this->actingAs($siswa);

    Livewire::test('pages::attendance.absensi.recap')
        ->call('exportExcel')
        ->assertForbidden();
});

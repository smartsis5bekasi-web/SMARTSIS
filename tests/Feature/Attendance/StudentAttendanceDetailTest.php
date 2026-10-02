<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Exports\StudentAttendanceExport;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Teacher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('staff see every attendance record of a student with arrival and departure times', function () {
    $student = Student::factory()->create(['name' => 'Budi Santoso']);
    $date = Carbon::parse('2026-05-04');

    Attendance::factory()->for($student)->create([
        'date' => $date->toDateString(),
        'checked_in_at' => $date->copy()->setTime(6, 41, 12),
        'checked_out_at' => $date->copy()->setTime(15, 5, 30),
    ]);
    Attendance::factory()->for($student)->late()->create(['date' => '2026-05-05']);
    Attendance::factory()->for(Student::factory())->create(['date' => '2026-05-06']);

    $this->actingAs(adminUser())
        ->get(route('attendance.absensi.student', $student))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('06:41:12 WIB')
        ->assertSee('15:05:30 WIB')
        ->assertDontSee('06/05/2026');
});

test('the detail page filters by period and status', function () {
    $student = Student::factory()->create();

    Attendance::factory()->for($student)->create(['date' => '2026-04-10']);
    Attendance::factory()->for($student)->late()->create(['date' => '2026-05-11']);
    Attendance::factory()->for($student)->create(['date' => '2026-05-12']);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::attendance.absensi.student', ['student' => $student])
        ->assertSee('10/04/2026')
        ->set('from', '2026-05-01')
        ->assertDontSee('10/04/2026')
        ->assertSee('11/05/2026')
        ->set('status', AttendanceStatus::Terlambat->value)
        ->assertSee('11/05/2026')
        ->assertDontSee('12/05/2026');

    expect($component->instance()->summary)
        ->toMatchArray(['total' => 2, 'hadir' => 1, 'terlambat' => 1]);
});

test('the student detail export follows the active filters', function () {
    $student = Student::factory()->create(['nis' => '1234500']);
    $date = Carbon::parse('2026-05-11');

    Attendance::factory()->for($student)->late()->create([
        'date' => $date->toDateString(),
        'checked_in_at' => $date->copy()->setTime(7, 31),
        'checked_out_at' => $date->copy()->setTime(15, 2),
        'note' => 'Ban bocor',
    ]);
    Attendance::factory()->for($student)->create(['date' => '2026-05-12']);

    $export = new StudentAttendanceExport($student, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), AttendanceStatus::Terlambat->value);
    $rows = $export->collection();

    expect($rows)->toHaveCount(1)
        ->and($export->map($rows->first()))->toMatchArray([0 => '11-05-2026', 2 => 'Terlambat', 3 => '07:31:00', 4 => '15:02:00', 5 => 'Ban bocor']);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.student', ['student' => $student])
        ->call('exportExcel')
        ->assertFileDownloaded('absensi-1234500-'.now()->format('Y-m-d').'.xlsx');
});

test('a wali kelas only opens the detail of their homeroom students', function () {
    $wali = userWithRole(UserRole::WaliKelas);
    $teacher = Teacher::factory()->create(['user_id' => $wali->id]);
    $homeroom = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);
    $mine = Student::factory()->create(['classroom_id' => $homeroom->id]);
    $theirs = Student::factory()->create();

    $this->actingAs($wali);

    $this->get(route('attendance.absensi.student', $mine))->assertOk();
    $this->get(route('attendance.absensi.student', $theirs))->assertForbidden();
});

test('a siswa only opens their own detail and cannot export it', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $own = Student::factory()->onboarded()->create(['user_id' => $siswa->id]);
    $other = Student::factory()->create();

    $this->actingAs($siswa);

    $this->get(route('attendance.absensi.student', $own))->assertOk()->assertDontSee('Export Excel');
    $this->get(route('attendance.absensi.student', $other))->assertForbidden();

    Livewire::test('pages::attendance.absensi.student', ['student' => $own])
        ->call('exportExcel')
        ->assertForbidden();
});

test('the rekap links each student to their attendance detail', function () {
    $student = Student::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('attendance.absensi.recap'))
        ->assertOk()
        ->assertSee(route('attendance.absensi.student', $student));
});

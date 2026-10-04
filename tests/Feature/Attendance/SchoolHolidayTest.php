<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\GradeLevel;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Exceptions\AttendanceException;
use App\Exports\AttendanceDailyExports;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Classroom;
use App\Models\PointRule;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DailyAttendanceReminder;
use App\Support\SchoolCalendar;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $alpha = PointRule::factory()->create([
        'name' => 'Alpha',
        'type' => PointType::Deduction,
        'source' => PointSource::Attendance,
        'point' => 5,
    ]);

    AttendanceSetting::current()->update([
        'check_in_start' => '06:00:00',
        'late_after' => '07:00:00',
        'check_out_after' => '15:00:00',
        'alpha_rule_id' => $alpha->id,
        'pending_alpha_after_days' => 3,
    ]);

    // A Thursday, an hour after the check-out window opened.
    Carbon::setTestNow(Carbon::parse('2026-10-08 16:00:00'));
});

/**
 * A student in a classroom of the given grade (none for a classroom whose
 * grade was never filled in).
 */
function studentInGrade(?GradeLevel $grade, bool $withAccount = false): Student
{
    $classroom = $grade === null
        ? Classroom::factory()->create(['name' => 'Kelas Akselerasi', 'grade' => null])
        : Classroom::factory()->grade($grade)->create();

    return Student::factory()->create([
        'classroom_id' => $classroom->id,
        'user_id' => $withAccount ? User::factory()->create()->id : null,
    ]);
}

// ---------------------------------------------------------------------------
// End-of-day sweep
// ---------------------------------------------------------------------------

test('a school-wide holiday skips the sweep even when someone did record attendance', function (bool $force) {
    SchoolHoliday::factory()->on('2026-10-08')->create(['name' => 'Cuti Bersama']);

    $present = studentInGrade(GradeLevel::Ten);
    $absent = studentInGrade(GradeLevel::Eleven);
    Attendance::factory()->for($present)->create();

    $this->artisan('attendance:mark-pending', $force ? ['--force' => true] : [])
        ->expectsOutputToContain('Cuti Bersama')
        ->assertSuccessful();

    expect($absent->attendances()->count())->toBe(0);
})->with(['scheduled run' => false, 'forced run' => true]);

test('a grade holiday leaves only those grades out of the sweep', function () {
    SchoolHoliday::factory()->on('2026-10-08')->forGrades(GradeLevel::Ten, GradeLevel::Eleven)->create();

    $tenth = studentInGrade(GradeLevel::Ten);
    $eleventh = studentInGrade(GradeLevel::Eleven);
    $twelfthPresent = studentInGrade(GradeLevel::Twelve);
    $twelfthAbsent = studentInGrade(GradeLevel::Twelve);
    $ungraded = studentInGrade(null);
    Attendance::factory()->for($twelfthPresent)->create();

    $this->artisan('attendance:mark-pending')->assertSuccessful();

    expect($tenth->attendances()->count())->toBe(0)
        ->and($eleventh->attendances()->count())->toBe(0)
        ->and($twelfthAbsent->attendances()->sole()->status)->toBe(AttendanceStatus::Pending)
        // Without a grade the classroom cannot be told apart, so it attends.
        ->and($ungraded->attendances()->sole()->status)->toBe(AttendanceStatus::Pending);
});

test('a holiday on another day does not affect the sweep', function () {
    SchoolHoliday::factory()->on('2026-10-09')->create();

    $present = studentInGrade(GradeLevel::Ten);
    $absent = studentInGrade(GradeLevel::Ten);
    Attendance::factory()->for($present)->create();

    $this->artisan('attendance:mark-pending')->assertSuccessful();

    expect($absent->attendances()->sole()->status)->toBe(AttendanceStatus::Pending);
});

// ---------------------------------------------------------------------------
// Recording attendance
// ---------------------------------------------------------------------------

test('a student who has the day off cannot check in, while other grades can', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 06:30:00'));
    SchoolHoliday::factory()->on('2026-10-08')->forGrades(GradeLevel::Ten)->create(['name' => 'Kegiatan Kelas XII']);

    $operator = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    expect(fn () => $engine->checkIn(studentInGrade(GradeLevel::Ten), $operator))
        ->toThrow(AttendanceException::class, 'Hari ini libur (Kegiatan Kelas XII)');

    expect($engine->checkIn(studentInGrade(GradeLevel::Twelve), $operator)->status)->toBe(AttendanceStatus::Hadir);
});

test('a student who has the day off cannot declare sakit or izin', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 06:30:00'));
    SchoolHoliday::factory()->on('2026-10-08')->create();

    $student = studentInGrade(GradeLevel::Ten);

    expect(fn () => app(RecordAttendance::class)->selfDeclare($student, AttendanceStatus::Sakit, userWithRole(UserRole::Siswa), '/storage/proof.png'))
        ->toThrow(AttendanceException::class);

    expect($student->attendances()->count())->toBe(0);
});

test('a student who checked in before the holiday was declared can still check out', function () {
    $student = studentInGrade(GradeLevel::Ten);
    Attendance::factory()->for($student)->create();
    SchoolHoliday::factory()->on('2026-10-08')->create();

    $attendance = app(RecordAttendance::class)->checkOut($student, userWithRole(UserRole::GuruPiket));

    expect($attendance->isCheckedOut())->toBeTrue();
});

test('the self-service page explains the holiday instead of opening the camera', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 06:30:00'));
    SchoolHoliday::factory()->on('2026-10-08')->forGrades(GradeLevel::Ten)->create(['name' => 'Libur Kelas X']);

    $siswa = userWithRole(UserRole::Siswa);
    $student = studentInGrade(GradeLevel::Ten);
    $student->update(['user_id' => $siswa->id, 'onboarded_at' => now(), 'face_registered_at' => now(), 'face_descriptors' => [[0.1]]]);
    $this->actingAs($siswa);

    Livewire::test('pages::attendance.absensi.index')
        ->assertSee('Hari ini libur: Libur Kelas X')
        ->assertDontSeeHtml('data-camera-video')
        ->assertDontSeeHtml("openDeclaration('sakit')")
        ->call('record', [])
        ->assertSet('lastResult.ok', false);

    expect($student->attendances()->count())->toBe(0);
});

test('tandai alpha skips the students who have the day off', function () {
    SchoolHoliday::factory()->on('2026-10-08')->forGrades(GradeLevel::Ten)->create();

    $tenth = studentInGrade(GradeLevel::Ten);
    $twelfth = studentInGrade(GradeLevel::Twelve);

    $marked = app(RecordAttendance::class)->markAbsentees(userWithRole(UserRole::GuruPiket), now());

    expect($marked)->toBe(1)
        ->and($tenth->attendances()->count())->toBe(0)
        ->and($twelfth->attendances()->sole()->status)->toBe(AttendanceStatus::Alpha);
});

// ---------------------------------------------------------------------------
// Reminders
// ---------------------------------------------------------------------------

test('no reminder goes out on a school-wide holiday', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-08 06:00:00'));
    SchoolHoliday::factory()->on('2026-10-08')->create();

    studentInGrade(GradeLevel::Ten, withAccount: true);

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

test('only the grades that attend are reminded on a grade holiday', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-08 06:00:00'));
    SchoolHoliday::factory()->on('2026-10-08')->forGrades(GradeLevel::Ten)->create();

    $tenth = studentInGrade(GradeLevel::Ten, withAccount: true);
    $twelfth = studentInGrade(GradeLevel::Twelve, withAccount: true);

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertSentTo($twelfth->user, DailyAttendanceReminder::class);
    Notification::assertNotSentTo($tenth->user, DailyAttendanceReminder::class);
});

// ---------------------------------------------------------------------------
// Alpha otomatis
// ---------------------------------------------------------------------------

test('a school-wide holiday does not count toward the confirmation deadline', function () {
    // Absent Monday; Tuesday and Wednesday the school is closed.
    SchoolHoliday::factory()->on('2026-10-06', '2026-10-07')->create();

    $deadline = AttendanceSetting::current()->pendingDeadline(Carbon::parse('2026-10-05'));

    // Thursday, Friday, then Monday.
    expect($deadline->toDateString())->toBe('2026-10-12');
});

test('a grade holiday does not move the confirmation deadline', function () {
    SchoolHoliday::factory()->on('2026-10-06', '2026-10-07')->forGrades(GradeLevel::Ten)->create();

    $deadline = AttendanceSetting::current()->pendingDeadline(Carbon::parse('2026-10-05'));

    expect($deadline->toDateString())->toBe('2026-10-08');
});

test('a pending day is not escalated while the school is closed', function () {
    SchoolHoliday::factory()->on('2026-10-06', '2026-10-07')->create();
    $student = studentInGrade(GradeLevel::Ten);
    $pending = Attendance::factory()->for($student)->pending()->create(['date' => '2026-10-05']);

    Carbon::setTestNow(Carbon::parse('2026-10-09 00:30:00'));
    $this->artisan('attendance:escalate-pending')->assertSuccessful();

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Pending);

    Carbon::setTestNow(Carbon::parse('2026-10-13 00:30:00'));
    $this->artisan('attendance:escalate-pending')->assertSuccessful();

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Alpha)
        ->and($student->fresh()->current_point)->toBe(95);
});

test('escalation drops a pending day that turned out to be a holiday instead of deducting', function () {
    $student = studentInGrade(GradeLevel::Ten);
    $pending = Attendance::factory()->for($student)->pending()->create(['date' => '2026-10-01']);
    // Entered straight into the table, so nothing has released it yet.
    SchoolHoliday::factory()->on('2026-10-01')->forGrades(GradeLevel::Ten)->create();

    $this->artisan('attendance:escalate-pending')->assertSuccessful();

    expect(Attendance::find($pending->id))->toBeNull()
        ->and($student->fresh()->current_point)->toBe(100);
});

// ---------------------------------------------------------------------------
// Declaring a holiday after the fact
// ---------------------------------------------------------------------------

test('declaring a past holiday releases pending and unreviewed automatic alpha but keeps what people recorded', function () {
    // Thursday 1 – Monday 5 October: the school closed for volcanic ash,
    // but nobody entered it until the following Thursday.
    $pendingStudent = studentInGrade(GradeLevel::Ten);
    $escalatedStudent = studentInGrade(GradeLevel::Ten);
    $manualAlphaStudent = studentInGrade(GradeLevel::Ten);
    $presentStudent = studentInGrade(GradeLevel::Ten);
    $otherGradeStudent = studentInGrade(GradeLevel::Twelve);

    $engine = app(RecordAttendance::class);
    $piket = userWithRole(UserRole::GuruPiket);

    Attendance::factory()->for($escalatedStudent)->pending()->create(['date' => '2026-10-01']);
    Attendance::factory()->for($otherGradeStudent)->pending()->create(['date' => '2026-10-01']);
    Carbon::setTestNow(Carbon::parse('2026-10-07 00:30:00'));
    $engine->escalatePending();

    Attendance::factory()->for($pendingStudent)->pending()->create(['date' => '2026-10-05']);
    $engine->markStatus($manualAlphaStudent, AttendanceStatus::Alpha, $piket, Carbon::parse('2026-10-02'));
    Attendance::factory()->for($presentStudent)->create(['date' => '2026-10-02']);

    expect($escalatedStudent->fresh()->current_point)->toBe(95);

    Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00'));
    $this->actingAs($piket);

    Livewire::test('pages::attendance.absensi.holidays.create')
        ->set('name', 'Libur darurat asap vulkanik')
        ->set('start_date', '2026-10-01')
        ->set('end_date', '2026-10-05')
        ->set('scope', 'grades')
        ->set('grades', ['10'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('attendance.absensi.holidays'));

    expect($pendingStudent->attendances()->count())->toBe(0)
        ->and($escalatedStudent->attendances()->count())->toBe(0)
        ->and($escalatedStudent->fresh()->current_point)->toBe(100)
        // A teacher's own decision and a real check-in are left alone.
        ->and($manualAlphaStudent->attendances()->sole()->status)->toBe(AttendanceStatus::Alpha)
        ->and($manualAlphaStudent->fresh()->current_point)->toBe(95)
        ->and($presentStudent->attendances()->sole()->status)->toBe(AttendanceStatus::Hadir)
        // Kelas XII was not on holiday.
        ->and($otherGradeStudent->attendances()->sole()->status)->toBe(AttendanceStatus::Alpha);
});

test('releasing a holiday never touches days that have not happened yet', function () {
    $student = studentInGrade(GradeLevel::Ten);
    Attendance::factory()->for($student)->pending()->create(['date' => '2026-10-08']);

    $holiday = SchoolHoliday::factory()->on('2026-10-09', '2026-10-12')->create();

    expect(app(RecordAttendance::class)->releaseHoliday($holiday))->toBe(0)
        ->and($student->attendances()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Managing holidays
// ---------------------------------------------------------------------------

test('a manager can declare a school-wide holiday', function () {
    $piket = userWithRole(UserRole::GuruPiket);
    $this->actingAs($piket);

    Livewire::test('pages::attendance.absensi.holidays.create')
        ->set('name', 'Cuti Bersama SKB 3 Menteri')
        ->set('start_date', '2026-12-24')
        ->assertSet('end_date', '2026-12-24')
        ->set('end_date', '2026-12-26')
        ->call('save')
        ->assertHasNoErrors();

    $holiday = SchoolHoliday::query()->sole();

    expect($holiday->name)->toBe('Cuti Bersama SKB 3 Menteri')
        ->and($holiday->start_date->toDateString())->toBe('2026-12-24')
        ->and($holiday->end_date->toDateString())->toBe('2026-12-26')
        ->and($holiday->isSchoolWide())->toBeTrue()
        ->and($holiday->created_by)->toBe($piket->id);
});

test('picking every grade is stored as a school-wide holiday', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.holidays.create')
        ->set('name', 'Libur')
        ->set('start_date', '2026-10-20')
        ->set('scope', 'grades')
        ->set('grades', ['10', '11', '12'])
        ->call('save')
        ->assertHasNoErrors();

    expect(SchoolHoliday::query()->sole()->grades)->toBeNull();
});

test('a grade holiday stores the grades picked', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.holidays.create')
        ->set('name', 'Kelas XII masuk, X dan XI libur')
        ->set('start_date', '2026-10-20')
        ->set('scope', 'grades')
        ->set('grades', ['11', '10'])
        ->call('save')
        ->assertHasNoErrors();

    $holiday = SchoolHoliday::query()->sole();

    expect($holiday->grades)->toBe([10, 11])
        ->and($holiday->scopeLabel())->toBe('Kelas X, Kelas XI');
});

test('the holiday form is validated', function (array $input, string $error) {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::attendance.absensi.holidays.create')
        ->set('name', 'Libur')
        ->set('start_date', '2026-10-20');

    foreach ($input as $property => $value) {
        $component->set($property, $value);
    }

    $component->call('save')->assertHasErrors($error);

    expect(SchoolHoliday::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'end before start' => [['end_date' => '2026-10-19'], 'end_date'],
    'longer than a year' => [['end_date' => '2027-10-21'], 'end_date'],
    'grades scope without grades' => [['scope' => 'grades', 'grades' => []], 'grades'],
    'unknown grade' => [['scope' => 'grades', 'grades' => ['9']], 'grades.0'],
]);

test('a holiday can be edited', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));
    $holiday = SchoolHoliday::factory()->on('2026-10-20')->create(['name' => 'Libur']);

    Livewire::test('pages::attendance.absensi.holidays.edit', ['holiday' => $holiday])
        ->assertSet('scope', 'all')
        ->set('name', 'Kegiatan kelas XII')
        ->set('scope', 'grades')
        ->set('grades', ['10', '11'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('attendance.absensi.holidays'));

    expect($holiday->fresh()->name)->toBe('Kegiatan kelas XII')
        ->and($holiday->fresh()->grades)->toBe([10, 11]);
});

test('a holiday can be deleted', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));
    $holiday = SchoolHoliday::factory()->on('2026-10-20')->create();

    Livewire::test('pages::attendance.absensi.holidays')
        ->assertSee($holiday->name)
        ->call('delete', $holiday->id);

    expect(SchoolHoliday::query()->count())->toBe(0);
});

test('the list shows upcoming holidays by default and past ones on request', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));
    SchoolHoliday::factory()->on('2026-10-20')->create(['name' => 'Libur Mendatang']);
    SchoolHoliday::factory()->on('2026-09-01')->create(['name' => 'Libur Lalu']);

    Livewire::test('pages::attendance.absensi.holidays')
        ->assertSee('Libur Mendatang')
        ->assertDontSee('Libur Lalu')
        ->set('period', 'past')
        ->assertSee('Libur Lalu')
        ->assertDontSee('Libur Mendatang');
});

test('only attendance managers can manage holidays', function (UserRole $role, bool $allowed) {
    $response = $this->actingAs(userWithRole($role))->get(route('attendance.absensi.holidays'));

    $allowed ? $response->assertSuccessful() : $response->assertForbidden();
})->with([
    'guru piket' => [UserRole::GuruPiket, true],
    'wali kelas' => [UserRole::WaliKelas, false],
    'guru mapel' => [UserRole::GuruMapel, false],
]);

// ---------------------------------------------------------------------------
// Monitoring
// ---------------------------------------------------------------------------

test('the daily monitor counts students on holiday apart from those who did not show up', function () {
    SchoolHoliday::factory()->on('2026-10-08')->forGrades(GradeLevel::Ten)->create(['name' => 'Kegiatan Kelas XII']);

    studentInGrade(GradeLevel::Ten);
    studentInGrade(GradeLevel::Ten);
    studentInGrade(GradeLevel::Twelve);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::attendance.absensi.index')
        ->assertSee('Hari libur: Kegiatan Kelas XII');

    expect($component->instance()->stats['holiday'])->toBe(2)
        ->and($component->instance()->stats['none'])->toBe(1);

    $component->set('status', 'none');
    expect($component->instance()->students->total())->toBe(1);

    $component->set('status', 'holiday');
    expect($component->instance()->students->total())->toBe(2);
});

test('the daily export labels students on holiday', function () {
    SchoolHoliday::factory()->on('2026-10-08')->forGrades(GradeLevel::Ten)->create(['name' => 'Kegiatan Kelas XII']);
    $tenth = studentInGrade(GradeLevel::Ten);
    studentInGrade(GradeLevel::Twelve);

    $export = new AttendanceDailyExports(date: Carbon::parse('2026-10-08'), status: 'holiday');
    $rows = $export->collection();

    expect($rows->pluck('id')->all())->toBe([$tenth->id])
        ->and($export->map($rows->first())[5])->toBe('Libur')
        ->and($export->map($rows->first())[6])->toBe('Kegiatan Kelas XII');
});

// ---------------------------------------------------------------------------
// Calendar
// ---------------------------------------------------------------------------

test('the calendar resolves overlapping holidays per grade', function () {
    SchoolHoliday::factory()->on('2026-10-05', '2026-10-09')->forGrades(GradeLevel::Ten)->create(['name' => 'Ujian Kelas XII']);
    SchoolHoliday::factory()->on('2026-10-07')->create(['name' => 'Libur Nasional']);

    $calendar = SchoolCalendar::between(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-09'));

    expect($calendar->gradesOffOn(Carbon::parse('2026-10-06')))->toBe([10])
        ->and($calendar->gradesOffOn(Carbon::parse('2026-10-07')))->toBe([10, 11, 12])
        ->and($calendar->holidayForGrade(GradeLevel::Ten, Carbon::parse('2026-10-07'))->name)->toBe('Libur Nasional')
        ->and($calendar->holidayForGrade(GradeLevel::Twelve, Carbon::parse('2026-10-06')))->toBeNull()
        ->and($calendar->holidayForGrade(null, Carbon::parse('2026-10-06')))->toBeNull()
        ->and($calendar->isSchoolDay(Carbon::parse('2026-10-06')))->toBeTrue()
        ->and($calendar->isSchoolDay(Carbon::parse('2026-10-07')))->toBeFalse()
        ->and($calendar->isSchoolDay(Carbon::parse('2026-10-10')))->toBeFalse();
});

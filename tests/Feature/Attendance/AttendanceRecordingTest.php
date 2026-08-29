<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\PointLog;
use App\Models\PointRule;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Wire the singleton attendance settings to fresh Terlambat (-2) and
 * Alpha (-5) rules, mirroring the PRD defaults.
 */
function setupAttendanceRules(): AttendanceSetting
{
    $late = PointRule::factory()->create([
        'name' => 'Terlambat',
        'type' => PointType::Deduction,
        'source' => PointSource::Attendance,
        'point' => 2,
    ]);

    $alpha = PointRule::factory()->create([
        'name' => 'Alpha',
        'type' => PointType::Deduction,
        'source' => PointSource::Attendance,
        'point' => 5,
    ]);

    $setting = AttendanceSetting::current();
    $setting->update([
        'check_in_start' => '06:00:00',
        'late_after' => '07:00:00',
        'check_out_after' => '15:00:00',
        'late_rule_id' => $late->id,
        'alpha_rule_id' => $alpha->id,
    ]);

    return $setting;
}

test('an on-time check-in records hadir without touching points', function () {
    setupAttendanceRules();
    Carbon::setTestNow(now()->setTime(6, 45));

    $student = Student::factory()->create();

    $attendance = app(RecordAttendance::class)->checkIn($student, userWithRole(UserRole::GuruPiket));

    expect($attendance->status)->toBe(AttendanceStatus::Hadir)
        ->and($attendance->checked_in_at)->not->toBeNull()
        ->and($student->fresh()->current_point)->toBe(100)
        ->and(PointLog::query()->count())->toBe(0);
});

test('a late check-in records terlambat and deducts the configured points', function () {
    setupAttendanceRules();
    Carbon::setTestNow(now()->setTime(7, 30));

    $student = Student::factory()->create();

    $attendance = app(RecordAttendance::class)->checkIn($student, userWithRole(UserRole::GuruPiket));

    $log = PointLog::query()->sole();

    expect($attendance->status)->toBe(AttendanceStatus::Terlambat)
        ->and($student->fresh()->current_point)->toBe(98)
        ->and($log->delta)->toBe(-2)
        ->and($log->source_id)->toBe($attendance->id)
        ->and($log->source_type)->toBe($attendance->getMorphClass());
});

test('a second check-in on the same day is rejected', function () {
    setupAttendanceRules();
    Carbon::setTestNow(now()->setTime(6, 45));

    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    $engine->checkIn($student, $piket);

    expect(fn () => $engine->checkIn($student, $piket))->toThrow(AttendanceException::class);
});

test('check-out stamps the record once the window opens', function () {
    setupAttendanceRules();

    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    Carbon::setTestNow(now()->setTime(6, 45));
    $engine->checkIn($student, $piket);

    Carbon::setTestNow(now()->setTime(15, 5));
    $attendance = $engine->checkOut($student, $piket);

    expect($attendance->isCheckedOut())->toBeTrue();
});

test('check-out is rejected before the window opens or without a check-in', function () {
    setupAttendanceRules();

    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    Carbon::setTestNow(now()->setTime(14, 0));

    // No check-in yet.
    expect(fn () => $engine->checkOut($student, $piket))->toThrow(AttendanceException::class);

    Carbon::setTestNow(now()->setTime(6, 45));
    $engine->checkIn($student, $piket);

    // Window not open yet.
    Carbon::setTestNow(now()->setTime(14, 0));
    expect(fn () => $engine->checkOut($student, $piket))->toThrow(AttendanceException::class);
});

test('marking alpha deducts the configured points', function () {
    setupAttendanceRules();

    $student = Student::factory()->create();

    $attendance = app(RecordAttendance::class)->markStatus(
        $student,
        AttendanceStatus::Alpha,
        userWithRole(UserRole::GuruPiket),
    );

    expect($attendance->status)->toBe(AttendanceStatus::Alpha)
        ->and($attendance->method)->toBe('manual')
        ->and($student->fresh()->current_point)->toBe(95);
});

test('correcting a penalized status reverses the deduction exactly once', function () {
    setupAttendanceRules();

    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    $engine->markStatus($student, AttendanceStatus::Alpha, $piket);
    expect($student->fresh()->current_point)->toBe(95);

    $engine->markStatus($student, AttendanceStatus::Sakit, $piket);
    expect($student->fresh()->current_point)->toBe(100);

    // A repeated no-op correction must not credit points again.
    $engine->markStatus($student, AttendanceStatus::Sakit, $piket);
    expect($student->fresh()->current_point)->toBe(100)
        ->and(Attendance::query()->count())->toBe(1);
});

test('correcting a late check-in to izin restores the points', function () {
    setupAttendanceRules();
    Carbon::setTestNow(now()->setTime(7, 30));

    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    $engine->checkIn($student, $piket);
    expect($student->fresh()->current_point)->toBe(98);

    $engine->markStatus($student, AttendanceStatus::Izin, $piket);
    expect($student->fresh()->current_point)->toBe(100);
});

test('the alpha sweep only marks students without a record', function () {
    setupAttendanceRules();
    Carbon::setTestNow(now()->setTime(16, 0));

    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    [$present, $absentA, $absentB] = Student::factory()->count(3)->create();
    Attendance::factory()->for($present)->create();

    $marked = $engine->markAbsentees($piket);

    expect($marked)->toBe(2)
        ->and($absentA->fresh()->current_point)->toBe(95)
        ->and($absentB->fresh()->current_point)->toBe(95)
        ->and($present->fresh()->current_point)->toBe(100)
        ->and(Attendance::query()->where('status', AttendanceStatus::Alpha)->count())->toBe(2);
});

test('no points are deducted when the setting has no rule wired', function () {
    // Defaults leave late/alpha unlinked; pin the times so the scenario does
    // not shift when the shipped defaults do.
    AttendanceSetting::current()->update([
        'check_in_start' => '06:00:00',
        'late_after' => '07:00:00',
    ]);
    Carbon::setTestNow(now()->setTime(7, 30));

    $student = Student::factory()->create();

    $attendance = app(RecordAttendance::class)->checkIn($student, userWithRole(UserRole::GuruPiket));

    expect($attendance->status)->toBe(AttendanceStatus::Terlambat)
        ->and($student->fresh()->current_point)->toBe(100)
        ->and(PointLog::query()->count())->toBe(0);
});

test('check-in before the window opens is rejected', function () {
    AttendanceSetting::current()->update(['check_in_start' => '06:00:00']);
    Carbon::setTestNow(now()->setTime(5, 30));

    $student = Student::factory()->create();

    expect(fn () => app(RecordAttendance::class)->checkIn($student, adminUser()))
        ->toThrow(AttendanceException::class, 'Absensi masuk baru dibuka pukul 06:00.');

    expect($student->attendances()->count())->toBe(0);
});

test('check-in exactly when the window opens is accepted', function () {
    AttendanceSetting::current()->update(['check_in_start' => '06:00:00']);
    Carbon::setTestNow(now()->setTime(6, 0));

    $student = Student::factory()->create();

    $attendance = app(RecordAttendance::class)->checkIn($student, adminUser());

    expect($attendance->status)->toBe(AttendanceStatus::Hadir);
});

test('a widened check-in window lets an earlier scan through', function () {
    AttendanceSetting::current()->update(['check_in_start' => '05:00:00']);
    Carbon::setTestNow(now()->setTime(5, 30));

    $student = Student::factory()->create();

    expect(app(RecordAttendance::class)->checkIn($student, adminUser())->status)
        ->toBe(AttendanceStatus::Hadir);
});

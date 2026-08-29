<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\PermitType;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Exceptions\AttendanceException;
use App\Models\AttendanceSetting;
use App\Models\Permit;
use App\Models\PointLog;
use App\Models\PointRule;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Attendance settings wired to a -2 Terlambat rule (07:00 / 15:00).
 */
function permitAttendanceSetting(): AttendanceSetting
{
    $late = PointRule::factory()->create([
        'name' => 'Terlambat',
        'type' => PointType::Deduction,
        'source' => PointSource::Attendance,
        'point' => 2,
    ]);

    $setting = AttendanceSetting::current();
    $setting->update([
        'check_in_start' => '06:00:00',
        'late_after' => '07:00:00',
        'check_out_after' => '15:00:00',
        'late_rule_id' => $late->id,
    ]);

    return $setting;
}

test('an approved izin terlambat waives the late point penalty', function () {
    permitAttendanceSetting();
    Carbon::setTestNow(now()->setTime(7, 30));

    $student = Student::factory()->create();
    Permit::factory()->for($student)->ofType(PermitType::Terlambat)->approved()->create(['date' => now()->toDateString()]);

    $attendance = app(RecordAttendance::class)->checkIn($student, userWithRole(UserRole::GuruPiket));

    expect($attendance->status)->toBe(AttendanceStatus::Terlambat)
        ->and($attendance->note)->not->toBeNull()
        ->and($student->fresh()->current_point)->toBe(100)
        ->and(PointLog::query()->count())->toBe(0);
});

test('a pending izin terlambat does not waive the penalty', function () {
    permitAttendanceSetting();
    Carbon::setTestNow(now()->setTime(7, 30));

    $student = Student::factory()->create();
    Permit::factory()->for($student)->ofType(PermitType::Terlambat)->create(['date' => now()->toDateString()]);

    app(RecordAttendance::class)->checkIn($student, userWithRole(UserRole::GuruPiket));

    expect($student->fresh()->current_point)->toBe(98);
});

test('an approved izin pulang awal opens check-out before the window', function () {
    permitAttendanceSetting();

    $student = Student::factory()->create();
    Permit::factory()->for($student)->ofType(PermitType::PulangAwal)->approved()->create(['date' => now()->toDateString()]);

    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    Carbon::setTestNow(now()->setTime(6, 45));
    $engine->checkIn($student, $piket);

    Carbon::setTestNow(now()->setTime(13, 0));
    $attendance = $engine->checkOut($student, $piket);

    expect($attendance->isCheckedOut())->toBeTrue()
        ->and($attendance->note)->toContain('Pulang awal');
});

test('early check-out is still rejected without an approved permit', function () {
    permitAttendanceSetting();

    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    Carbon::setTestNow(now()->setTime(6, 45));
    $engine->checkIn($student, $piket);

    Carbon::setTestNow(now()->setTime(13, 0));

    expect(fn () => $engine->checkOut($student, $piket))->toThrow(AttendanceException::class);
});

test('a manual terlambat correction also honors the approved permit', function () {
    permitAttendanceSetting();

    $student = Student::factory()->create();
    Permit::factory()->for($student)->ofType(PermitType::Terlambat)->approved()->create(['date' => now()->toDateString()]);

    app(RecordAttendance::class)->markStatus($student, AttendanceStatus::Terlambat, userWithRole(UserRole::GuruPiket));

    expect($student->fresh()->current_point)->toBe(100);
});

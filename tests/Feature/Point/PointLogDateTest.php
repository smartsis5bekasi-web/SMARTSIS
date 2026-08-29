<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Models\AttendanceSetting;
use App\Models\PointLog;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Violation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $alpha = PointRule::factory()->create([
        'name' => 'Alpha',
        'type' => PointType::Deduction,
        'source' => PointSource::Attendance,
        'point' => 5,
    ]);

    AttendanceSetting::current()->update(['alpha_rule_id' => $alpha->id]);
});

test('marking a backlog of absences records one row per day, not duplicates', function () {
    $student = Student::factory()->create();
    $by = userWithRole(UserRole::GuruPiket);
    $days = ['2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13'];

    // The guru piket catches up on four school days in one sitting.
    Carbon::setTestNow(Carbon::parse('2026-08-13 16:00:00'));

    foreach ($days as $day) {
        app(RecordAttendance::class)->markAbsentees($by, Carbon::parse($day));
    }

    expect($student->attendances()->pluck('date')->map->toDateString()->all())->toBe($days)
        ->and(PointLog::query()->count())->toBe(4)
        ->and($student->fresh()->current_point)->toBe(80);
});

test('sweeping the same day twice does not deduct twice', function () {
    $student = Student::factory()->create();
    $by = userWithRole(UserRole::GuruPiket);

    app(RecordAttendance::class)->markAbsentees($by, today());
    app(RecordAttendance::class)->markAbsentees($by, today());

    expect($student->attendances()->count())->toBe(1)
        ->and(PointLog::query()->count())->toBe(1)
        ->and($student->fresh()->current_point)->toBe(95);
});

test('a point entry is dated by when it happened, not when it was recorded', function () {
    $student = Student::factory()->create();
    $by = userWithRole(UserRole::GuruPiket);

    Carbon::setTestNow(Carbon::parse('2026-08-13 16:00:00'));
    app(RecordAttendance::class)->markAbsentees($by, Carbon::parse('2026-08-10'));

    $log = PointLog::query()->sole();

    expect($log->created_at->toDateString())->toBe('2026-08-13')
        ->and($log->occurredAt()->toDateString())->toBe('2026-08-10');
});

test('a violation entry is dated by the day it occurred', function () {
    $student = Student::factory()->create();
    $rule = PointRule::factory()->create([
        'type' => PointType::Deduction,
        'source' => PointSource::Violation,
        'point' => 30,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-08-13 16:00:00'));

    $violation = Violation::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
        'occurred_on' => '2026-08-06',
    ]);
    $violation->approve(userWithRole(UserRole::GuruBk));

    $log = PointLog::query()->sole();

    expect($log->occurredAt()->toDateString())->toBe('2026-08-06');
});

test('a manual adjustment with no source falls back to when it was written', function () {
    $log = PointLog::factory()->create(['source_type' => null, 'source_id' => null]);

    expect($log->occurredAt()->toDateString())->toBe($log->created_at->toDateString());
});

test('the student point page shows the day of the absence', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $siswa->id]);
    $by = userWithRole(UserRole::GuruPiket);

    Carbon::setTestNow(Carbon::parse('2026-08-13 16:00:00'));

    foreach (['2026-08-10', '2026-08-11'] as $day) {
        app(RecordAttendance::class)->markAbsentees($by, Carbon::parse($day));
    }

    $html = Livewire::actingAs($siswa)
        ->test('pages::attendance.point.show', ['student' => $student])
        ->html();

    // Both absences are listed under their own day, not the day they were swept.
    expect($html)->toContain('10 Aug 2026')
        ->and($html)->toContain('11 Aug 2026');
});

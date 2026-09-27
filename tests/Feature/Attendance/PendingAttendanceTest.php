<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\Permission;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Classroom;
use App\Models\PointLog;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    AttendanceSetting::current()->update([
        'check_in_start' => '06:00:00',
        'late_after' => '07:00:00',
        'check_out_after' => '15:00:00',
        'alpha_rule_id' => $alpha->id,
    ]);

    // A Thursday, an hour after the check-out window opened.
    Carbon::setTestNow(Carbon::parse('2026-09-24 16:00:00'));
});

test('a student who never showed up is recorded as pending without losing points', function () {
    [$present, $absent] = Student::factory()->count(2)->create();
    Attendance::factory()->for($present)->create();

    $this->artisan('attendance:mark-pending')->assertSuccessful();

    $record = $absent->attendances()->sole();

    expect($record->status)->toBe(AttendanceStatus::Pending)
        ->and($record->date->toDateString())->toBe('2026-09-24')
        ->and($record->isVerified())->toBeFalse()
        ->and($absent->fresh()->current_point)->toBe(100)
        ->and(PointLog::query()->count())->toBe(0)
        ->and($present->attendances()->sole()->status)->toBe(AttendanceStatus::Hadir);
});

test('running the sweep again does not duplicate records', function () {
    [$present, $absent] = Student::factory()->count(2)->create();
    Attendance::factory()->for($present)->create();

    $this->artisan('attendance:mark-pending')->assertSuccessful();
    $this->artisan('attendance:mark-pending')->assertSuccessful();

    expect($absent->attendances()->count())->toBe(1)
        ->and(Attendance::query()->count())->toBe(2);
});

test('the sweep waits until the school day is over', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));

    [$present, $absent] = Student::factory()->count(2)->create();
    Attendance::factory()->for($present)->create();

    $this->artisan('attendance:mark-pending')->assertSuccessful();

    expect($absent->attendances()->count())->toBe(0);
});

test('weekends are skipped', function () {
    [$present, $absent] = Student::factory()->count(2)->create(['created_at' => '2026-09-01']);
    // The Saturday before; a club or make-up class may still leave a record.
    Attendance::factory()->for($present)->create(['date' => '2026-09-19']);

    $this->artisan('attendance:mark-pending', ['--date' => '2026-09-19'])->assertSuccessful();

    expect($absent->attendances()->count())->toBe(0);
});

test('a day with no attendance at all is treated as a holiday unless forced', function () {
    $student = Student::factory()->create();

    $this->artisan('attendance:mark-pending')->assertSuccessful();

    expect($student->attendances()->count())->toBe(0);

    $this->artisan('attendance:mark-pending', ['--force' => true])->assertSuccessful();

    expect($student->attendances()->sole()->status)->toBe(AttendanceStatus::Pending);
});

test('a future date is refused', function () {
    $this->artisan('attendance:mark-pending', ['--date' => '2026-09-25'])->assertFailed();

    expect(Attendance::query()->count())->toBe(0);
});

test('backfilling a past day leaves out deactivated accounts and students enrolled later', function () {
    $enrolled = ['created_at' => '2026-09-01'];

    $present = Student::factory()->create($enrolled);
    $absent = Student::factory()->create($enrolled);
    $withoutLogin = Student::factory()->create([...$enrolled, 'user_id' => null]);
    $deactivated = Student::factory()->create([
        ...$enrolled,
        'user_id' => User::factory()->create(['is_active' => false])->id,
    ]);
    $enrolledLater = Student::factory()->create(['created_at' => '2026-09-23']);

    Attendance::factory()->for($present)->create(['date' => '2026-09-22']);

    $this->artisan('attendance:mark-pending', ['--date' => '2026-09-22'])->assertSuccessful();

    expect($absent->attendances()->sole()->status)->toBe(AttendanceStatus::Pending)
        ->and($withoutLogin->attendances()->count())->toBe(1)
        ->and($deactivated->attendances()->count())->toBe(0)
        ->and($enrolledLater->attendances()->count())->toBe(0);
});

test('a late scan replaces the pending placeholder', function () {
    $student = Student::factory()->create();
    $pending = Attendance::factory()->for($student)->pending()->create();

    $attendance = app(RecordAttendance::class)->checkIn($student, userWithRole(UserRole::GuruPiket));

    expect($attendance->id)->toBe($pending->id)
        ->and($attendance->status)->toBe(AttendanceStatus::Terlambat)
        ->and($attendance->checked_in_at)->not->toBeNull()
        ->and($student->attendances()->count())->toBe(1);
});

test('a student can still declare sakit over a pending day', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $siswa->id]);
    Attendance::factory()->for($student)->pending()->create();

    $attendance = app(RecordAttendance::class)->selfDeclare(
        student: $student,
        status: AttendanceStatus::Sakit,
        by: $siswa,
        attachmentPath: '/storage/attendances/proofs/surat.jpg',
    );

    expect($attendance->status)->toBe(AttendanceStatus::Sakit)
        ->and($attendance->isVerified())->toBeFalse()
        ->and($student->attendances()->count())->toBe(1);
});

test('the siswa absensi page still offers the scan over a pending day', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $siswa->id]);
    Attendance::factory()->for($student)->pending()->create();

    $this->actingAs($siswa);

    $component = Livewire::test('pages::attendance.absensi.index');

    expect($component->instance()->nextScanStep())->toBe('masuk');
});

test('confirming a pending day as alpha applies the alpha rule', function () {
    $student = Student::factory()->create();
    $pending = Attendance::factory()->for($student)->pending()->create(['date' => '2026-09-22']);
    $piket = userWithRole(UserRole::GuruPiket);

    $this->actingAs($piket);

    Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $pending->id, 'alpha')
        ->assertOk();

    $record = $pending->fresh();

    expect($record->status)->toBe(AttendanceStatus::Alpha)
        ->and($record->date->toDateString())->toBe('2026-09-22')
        ->and($record->verified_by)->toBe($piket->id)
        ->and($student->fresh()->current_point)->toBe(95)
        ->and(PointLog::query()->sole()->occurredAt()->toDateString())->toBe('2026-09-22');
});

test('confirming a pending day as sakit or izin costs no points', function (string $status) {
    $student = Student::factory()->create();
    $pending = Attendance::factory()->for($student)->pending()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $pending->id, $status)
        ->assertOk();

    expect($pending->fresh()->status->value)->toBe($status)
        ->and($student->fresh()->current_point)->toBe(100)
        ->and(PointLog::query()->count())->toBe(0);
})->with(['sakit', 'izin']);

test('only managers can confirm a pending day', function () {
    $pending = Attendance::factory()->for(Student::factory())->pending()->create();

    $this->actingAs(userWithRole(UserRole::GuruMapel));

    Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $pending->id, 'alpha')
        ->assertStatus(403);

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Pending);
});

test('pending is never a status a teacher can pick', function () {
    $student = Student::factory()->create();
    $attendance = Attendance::factory()->for($student)->alpha()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $attendance->id, 'pending')
        ->assertOk();

    expect($attendance->fresh()->status)->toBe(AttendanceStatus::Alpha)
        ->and(fn () => app(RecordAttendance::class)->markStatus($student, AttendanceStatus::Pending, adminUser()))
        ->toThrow(AttendanceException::class);
});

test('an unverified sakit can be overruled as alpha, or verified by confirming it as is', function () {
    $overruled = Attendance::factory()->for(Student::factory())->sakit()->unverified()->create();
    $confirmed = Attendance::factory()->for(Student::factory())->sakit()->unverified()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $overruled->id, 'alpha')
        ->call('resolve', $confirmed->id, 'sakit');

    expect($overruled->fresh()->status)->toBe(AttendanceStatus::Alpha)
        ->and($overruled->student->fresh()->current_point)->toBe(95)
        ->and($confirmed->fresh()->status)->toBe(AttendanceStatus::Sakit)
        ->and($confirmed->fresh()->isVerified())->toBeTrue();
});

test('a pending day has nothing to verify', function () {
    $pending = Attendance::factory()->for(Student::factory())->pending()->create();

    app(RecordAttendance::class)->verify($pending, adminUser());

    expect($pending->fresh()->isVerified())->toBeFalse();
});

test('the bulk alpha action also settles pending days', function () {
    $student = Student::factory()->create();
    Attendance::factory()->for($student)->pending()->create();

    $marked = app(RecordAttendance::class)->markAbsentees(userWithRole(UserRole::GuruPiket));

    expect($marked)->toBe(1)
        ->and($student->attendances()->sole()->status)->toBe(AttendanceStatus::Alpha)
        ->and($student->fresh()->current_point)->toBe(95);
});

test('the bulk alpha action leaves deactivated accounts alone', function () {
    $student = Student::factory()->create([
        'user_id' => User::factory()->create(['is_active' => false])->id,
    ]);

    app(RecordAttendance::class)->markAbsentees(userWithRole(UserRole::GuruPiket));

    expect($student->attendances()->count())->toBe(0)
        ->and($student->fresh()->current_point)->toBe(100);
});

test('a wali kelas granted attendance management only reaches their own class', function () {
    $wali = userWithRole(UserRole::WaliKelas);
    $wali->givePermissionTo(Permission::ManageAttendance->value);
    $teacher = Teacher::factory()->create(['user_id' => $wali->id]);
    $homeroom = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);

    $mine = Student::factory()->create(['classroom_id' => $homeroom->id]);
    $theirs = Student::factory()->create();
    $minePending = Attendance::factory()->for($mine)->pending()->create();
    $theirsPending = Attendance::factory()->for($theirs)->pending()->create();

    $this->actingAs($wali);

    Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $minePending->id, 'izin')
        ->assertOk();

    expect(fn () => Livewire::test('pages::attendance.absensi.history')->call('resolve', $theirsPending->id, 'alpha'))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => Livewire::test('pages::attendance.absensi.index')->call('markStatus', $theirs->id, 'alpha'))
        ->toThrow(ModelNotFoundException::class);

    expect($minePending->fresh()->status)->toBe(AttendanceStatus::Izin)
        ->and($theirsPending->fresh()->status)->toBe(AttendanceStatus::Pending);
});

test('the riwayat page points a manager at the days waiting for confirmation', function () {
    Attendance::factory()->for(Student::factory()->create(['name' => 'Siswa Tanpa Kabar']))->pending()->create(['date' => '2026-08-12']);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.history')
        ->assertSee('1 absensi siswa menunggu konfirmasi')
        ->assertDontSee('Siswa Tanpa Kabar')
        ->call('showPending')
        ->assertSet('status', 'pending')
        ->assertSee('Siswa Tanpa Kabar')
        ->assertSee('Menunggu Konfirmasi');
});

test('the guru piket dashboard counts days waiting for confirmation', function () {
    Attendance::factory()->count(2)->for(Student::factory())->pending()->sequence(
        ['date' => '2026-09-23'],
        ['date' => '2026-09-24'],
    )->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Absensi Menunggu Konfirmasi');
});

test('the sweep is scheduled on weekdays', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'attendance:mark-pending'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * 1-5');
});

<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\ParentGuardian;
use App\Models\PointLog;
use App\Models\PointRule;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->alphaRule = PointRule::factory()->create([
        'name' => 'Alpha',
        'type' => PointType::Deduction,
        'source' => PointSource::Attendance,
        'point' => 5,
    ]);

    AttendanceSetting::current()->update([
        'alpha_rule_id' => $this->alphaRule->id,
        'pending_alpha_after_days' => 3,
    ]);
});

/**
 * A student left "Menunggu Konfirmasi" on the given day by the sweep.
 */
function pendingDay(string $date, ?Student $student = null): Attendance
{
    return Attendance::factory()
        ->for($student ?? Student::factory()->create())
        ->pending()
        ->create(['date' => $date]);
}

test('a pending day nobody confirmed within three school days becomes alpha and loses the alpha points', function () {
    // Absent on Monday; Tuesday–Thursday pass with no confirmation.
    $pending = pendingDay('2026-09-21');
    Carbon::setTestNow(Carbon::parse('2026-09-25 00:30:00'));

    $this->artisan('attendance:escalate-pending')->assertSuccessful();

    $record = $pending->fresh();
    $log = PointLog::query()->sole();

    expect($record->status)->toBe(AttendanceStatus::Alpha)
        ->and($record->isEscalated())->toBeTrue()
        ->and($record->needsEscalationReview())->toBeTrue()
        ->and($record->method)->toBe('system')
        ->and($record->student->fresh()->current_point)->toBe(95)
        ->and($log->delta)->toBe(-5)
        ->and($log->created_by)->toBeNull()
        ->and($log->note)->toContain('otomatis')
        ->and($log->occurredAt()->toDateString())->toBe('2026-09-21');
});

test('the deduction follows whatever the alpha rule is set to', function () {
    $this->alphaRule->update(['point' => 7]);
    $pending = pendingDay('2026-09-21');
    Carbon::setTestNow(Carbon::parse('2026-09-25 00:30:00'));

    app(RecordAttendance::class)->escalatePending();

    expect($pending->student->fresh()->current_point)->toBe(93);
});

test('a pending day is left alone until its last confirmation day is over', function () {
    $pending = pendingDay('2026-09-21');
    Carbon::setTestNow(Carbon::parse('2026-09-24 23:00:00'));

    $this->artisan('attendance:escalate-pending')->assertSuccessful();

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Pending)
        ->and(PointLog::query()->count())->toBe(0);
});

test('weekends do not count toward the confirmation window', function () {
    // Absent on Friday: the teacher has Monday, Tuesday and Wednesday.
    $pending = pendingDay('2026-09-18');

    Carbon::setTestNow(Carbon::parse('2026-09-23 22:00:00'));
    app(RecordAttendance::class)->escalatePending();

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Pending);

    Carbon::setTestNow(Carbon::parse('2026-09-24 00:30:00'));
    app(RecordAttendance::class)->escalatePending();

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Alpha);
});

test('running the escalation again deducts only once', function () {
    $pending = pendingDay('2026-09-21');
    Carbon::setTestNow(Carbon::parse('2026-09-25 00:30:00'));

    $this->artisan('attendance:escalate-pending')->assertSuccessful();
    $this->artisan('attendance:escalate-pending')->assertSuccessful();

    expect(PointLog::query()->count())->toBe(1)
        ->and($pending->student->fresh()->current_point)->toBe(95);
});

test('a day a teacher already confirmed is never escalated', function () {
    $pending = pendingDay('2026-09-21');
    app(RecordAttendance::class)->markStatus($pending->student, AttendanceStatus::Sakit, userWithRole(UserRole::GuruPiket), Carbon::parse('2026-09-21'));

    Carbon::setTestNow(Carbon::parse('2026-09-25 00:30:00'));
    app(RecordAttendance::class)->escalatePending();

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Sakit)
        ->and($pending->student->fresh()->current_point)->toBe(100);
});

test('the automatic alpha can be switched off', function () {
    AttendanceSetting::current()->update(['pending_alpha_after_days' => 0]);
    $pending = pendingDay('2026-09-01');
    Carbon::setTestNow(Carbon::parse('2026-09-25 00:30:00'));

    $this->artisan('attendance:escalate-pending')->assertSuccessful();

    expect($pending->fresh()->status)->toBe(AttendanceStatus::Pending);
});

test('a teacher can still overrule an automatic alpha and the points come back', function () {
    $pending = pendingDay('2026-09-21');
    Carbon::setTestNow(Carbon::parse('2026-09-25 08:00:00'));
    app(RecordAttendance::class)->escalatePending();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $pending->id, 'sakit')
        ->assertOk();

    $record = $pending->fresh();

    expect($record->status)->toBe(AttendanceStatus::Sakit)
        ->and($record->isEscalated())->toBeFalse()
        ->and($record->student->fresh()->current_point)->toBe(100)
        ->and((int) $record->pointLogs()->sum('delta'))->toBe(0);
});

test('confirming an automatic alpha keeps the deduction and clears the review label', function () {
    $pending = pendingDay('2026-09-21');
    Carbon::setTestNow(Carbon::parse('2026-09-25 08:00:00'));
    app(RecordAttendance::class)->escalatePending();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::attendance.absensi.history')
        ->call('resolve', $pending->id, 'alpha');

    $record = $pending->fresh();

    expect($record->status)->toBe(AttendanceStatus::Alpha)
        ->and($record->isVerified())->toBeTrue()
        ->and($record->needsEscalationReview())->toBeFalse()
        ->and($record->student->fresh()->current_point)->toBe(95)
        ->and($component->instance()->escalatedCount)->toBe(0);
});

test('flipping a status back and forth never drifts the balance', function () {
    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);
    $engine = app(RecordAttendance::class);

    foreach ([AttendanceStatus::Alpha, AttendanceStatus::Sakit, AttendanceStatus::Alpha, AttendanceStatus::Izin, AttendanceStatus::Alpha] as $status) {
        $engine->markStatus($student, $status, $piket);
    }

    expect($student->fresh()->current_point)->toBe(95);

    $engine->markStatus($student, AttendanceStatus::Hadir, $piket);

    expect($student->fresh()->current_point)->toBe(100)
        ->and((int) $student->pointLogs()->sum('delta'))->toBe(0);
});

test('a balance floored at zero is refunded only what it actually lost', function () {
    $student = Student::factory()->create(['current_point' => 3]);
    $piket = userWithRole(UserRole::GuruPiket);

    app(RecordAttendance::class)->markStatus($student, AttendanceStatus::Alpha, $piket);

    expect($student->fresh()->current_point)->toBe(0)
        ->and(PointLog::query()->sole()->delta)->toBe(-3);

    app(RecordAttendance::class)->markStatus($student, AttendanceStatus::Izin, $piket);

    expect($student->fresh()->current_point)->toBe(3);
});

test('pending and automatic alpha days carry their warning labels', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00'));

    pendingDay('2026-09-21', Student::factory()->create(['name' => 'Siswa Menunggu']));
    $escalated = pendingDay('2026-09-14', Student::factory()->create(['name' => 'Siswa Alpha Otomatis']));
    app(RecordAttendance::class)->escalatePending();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.history')
        ->assertSee('Batas konfirmasi '.Carbon::parse('2026-09-24')->translatedFormat('D, d M'))
        ->assertSee('Alpha otomatis · belum dicek')
        ->assertSee('1 Alpha otomatis belum dicek guru')
        ->call('showEscalated')
        ->assertSee('Siswa Alpha Otomatis')
        ->assertDontSee('Siswa Menunggu');

    expect($escalated->fresh()->status)->toBe(AttendanceStatus::Alpha);
});

test('the parent of an absent child sees the automatic alpha label', function () {
    $ortu = userWithRole(UserRole::OrangTua);
    $parent = ParentGuardian::factory()->create(['user_id' => $ortu->id]);
    $student = Student::factory()->create();
    $parent->students()->attach($student->id, ['relationship' => 'Ibu']);

    pendingDay('2026-09-14', $student);
    Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00'));
    app(RecordAttendance::class)->escalatePending();

    $this->actingAs($ortu);

    Livewire::test('pages::attendance.absensi.index')
        ->set('month', '2026-09')
        ->assertSee('Alpha otomatis · belum dicek');
});

test('managers are warned when alpha would not deduct any points', function () {
    AttendanceSetting::current()->update(['alpha_rule_id' => null]);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.history')
        ->assertSee('Aturan Poin Alpha belum dipilih');

    AttendanceSetting::current()->update(['alpha_rule_id' => $this->alphaRule->id]);
    $this->alphaRule->update(['is_active' => false]);

    Livewire::test('pages::attendance.absensi.history')
        ->assertSee('Aturan Poin Alpha belum dipilih');
});

test('the confirmation window is configurable from the settings page', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.settings')
        ->set('pending_alpha_after_days', -1)
        ->call('save')
        ->assertHasErrors(['pending_alpha_after_days']);

    Livewire::test('pages::attendance.absensi.settings')
        ->set('pending_alpha_after_days', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(AttendanceSetting::current()->pending_alpha_after_days)->toBe(5);
});

test('the escalation runs every hour', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'attendance:escalate-pending'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});

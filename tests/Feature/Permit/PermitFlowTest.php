<?php

use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\Permit;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * A signed-in siswa with a linked, onboarded student record.
 *
 * @return array{0: User, 1: Student}
 */
function siswaWithStudent(): array
{
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $user->id]);

    return [$user, $student];
}

/**
 * A wali kelas whose homeroom contains the given student.
 */
function walasFor(Student $student): User
{
    $user = userWithRole(UserRole::WaliKelas);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    $classroom = $student->classroom ?? Classroom::factory()->create();
    $classroom->update(['homeroom_teacher_id' => $teacher->id]);
    $student->update(['classroom_id' => $classroom->id]);

    return $user;
}

test('a siswa can submit a permit request', function () {
    [$user, $student] = siswaWithStudent();

    $this->actingAs($user);

    Livewire::test('pages::permit.create')
        ->set('type', PermitType::Keluar->value)
        ->set('date', now()->toDateString())
        ->set('reason', 'Antar adik berobat ke puskesmas.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('permits.index'));

    $permit = $student->permits()->sole();

    expect($permit->type)->toBe(PermitType::Keluar)
        ->and($permit->status)->toBe(PermitStatus::Pending);
});

test('a duplicate request for the same type and date is rejected', function () {
    [$user, $student] = siswaWithStudent();
    Permit::factory()->for($student)->ofType(PermitType::Keluar)->create(['date' => now()->toDateString()]);

    $this->actingAs($user);

    Livewire::test('pages::permit.create')
        ->set('type', PermitType::Keluar->value)
        ->set('date', now()->toDateString())
        ->set('reason', 'Duplikat.')
        ->call('save')
        ->assertHasErrors('type');

    expect($student->permits()->count())->toBe(1);
});

test('a permit cannot be requested for a past date', function () {
    [$user] = siswaWithStudent();

    $this->actingAs($user);

    Livewire::test('pages::permit.create')
        ->set('type', PermitType::Keluar->value)
        ->set('date', now()->subDay()->toDateString())
        ->set('reason', 'Mundur.')
        ->call('save')
        ->assertHasErrors('date');
});

test('izin terlambat is no longer offered on the request form', function () {
    [$user] = siswaWithStudent();

    $this->actingAs($user);

    $component = Livewire::test('pages::permit.create');

    expect($component->instance()->types())->not->toContain(PermitType::Terlambat);

    $component
        ->set('type', PermitType::Terlambat->value)
        ->set('date', now()->toDateString())
        ->set('reason', 'Terlambat karena macet.')
        ->call('save')
        ->assertHasErrors('type');
});

test('izin terlambat is no longer offered on the manual form', function () {
    $student = Student::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::permit.manual');

    expect($component->instance()->types())->not->toContain(PermitType::Terlambat);

    $component
        ->set('student_id', $student->id)
        ->set('type', PermitType::Terlambat->value)
        ->set('date', now()->toDateString())
        ->set('reason', 'Terlambat karena macet.')
        ->call('save')
        ->assertHasErrors('type');

    expect($student->permits()->count())->toBe(0);
});

test('an account without a student record cannot open the request form', function () {
    $this->actingAs(userWithRole(UserRole::SuperAdmin));

    Livewire::test('pages::permit.create')
        ->assertStatus(403);
});

test('guru piket can approve a pending permit', function () {
    $permit = Permit::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('approve')
        ->assertRedirect(route('permits.index'));

    $permit->refresh();
    expect($permit->status)->toBe(PermitStatus::Approved)
        ->and($permit->decided_at)->not->toBeNull();
});

test('rejecting a permit requires a note', function () {
    $permit = Permit::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('reject')
        ->assertHasErrors('note');

    expect($permit->fresh()->isPending())->toBeTrue();

    $component->set('note', 'Alasan tidak dapat diterima.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($permit->fresh()->status)->toBe(PermitStatus::Rejected);
});

test('a wali kelas can approve permits of their homeroom students only', function () {
    [, $student] = siswaWithStudent();
    $permit = Permit::factory()->for($student)->create();
    $walas = walasFor($student);

    $this->actingAs($walas);

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('approve');

    // The wali kelas signs off, but the Guru Piket decision is still pending.
    $permit->refresh();
    expect($permit->status)->toBe(PermitStatus::Pending)
        ->and($permit->homeroom_approved_by)->toBe($walas->id)
        ->and($permit->statusLabel())->toBe('Menunggu Guru Piket');

    // A permit from another class is not even viewable.
    $otherPermit = Permit::factory()->create();

    Livewire::test('pages::permit.show', ['permit' => $otherPermit])
        ->assertStatus(403);
});

test('viewer roles cannot decide a permit', function () {
    $permit = Permit::factory()->create();

    $this->actingAs(userWithRole(UserRole::KepalaSekolah));

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('approve')
        ->assertStatus(403);

    expect($permit->fresh()->isPending())->toBeTrue();
});

test('a decided permit can never be re-decided', function () {
    $permit = Permit::factory()->rejected()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('approve')
        ->assertStatus(403);

    expect($permit->fresh()->status)->toBe(PermitStatus::Rejected);
});

test('a siswa can cancel their own pending request but not a decided one', function () {
    [$user, $student] = siswaWithStudent();
    $pending = Permit::factory()->for($student)->create();

    $this->actingAs($user);

    Livewire::test('pages::permit.show', ['permit' => $pending])
        ->call('cancel')
        ->assertRedirect(route('permits.index'));

    expect(Permit::query()->find($pending->id))->toBeNull();

    $approved = Permit::factory()->for($student)->approved()->create();

    Livewire::test('pages::permit.show', ['permit' => $approved])
        ->call('cancel')
        ->assertStatus(403);
});

test('a siswa cannot view another student\'s permit', function () {
    [$user] = siswaWithStudent();
    $otherPermit = Permit::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::permit.show', ['permit' => $otherPermit])
        ->assertStatus(403);
});

test('an orang tua sees their child\'s permits read-only', function () {
    $ortu = userWithRole(UserRole::OrangTua);
    $parent = ParentGuardian::factory()->create(['user_id' => $ortu->id]);
    $student = Student::factory()->create();
    $parent->students()->attach($student->id, ['relationship' => 'Ibu']);

    $permit = Permit::factory()->for($student)->create();

    $this->actingAs($ortu)
        ->get(route('permits.show', $permit))
        ->assertOk();

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('approve')
        ->assertStatus(403);
});

test('guru mapel has no access to the permit module', function () {
    $this->actingAs(userWithRole(UserRole::GuruMapel))
        ->get(route('permits.index'))
        ->assertForbidden();
});

test('roles with view access can open the permit index', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('permits.index'))
        ->assertOk();
})->with([
    'kepala sekolah' => [UserRole::KepalaSekolah],
    'wakasek kesiswaan' => [UserRole::WakasekKesiswaan],
    'guru bk' => [UserRole::GuruBk],
    'wali kelas' => [UserRole::WaliKelas],
    'guru piket' => [UserRole::GuruPiket],
]);

test('guru piket can record a walk-in permit manually and it lands approved', function () {
    $student = Student::factory()->create();
    $piket = userWithRole(UserRole::GuruPiket);

    $this->actingAs($piket);

    Livewire::test('pages::permit.manual')
        ->set('student_id', $student->id)
        ->set('type', PermitType::Keluar->value)
        ->set('date', now()->toDateString())
        ->set('reason', 'Dijemput orang tua untuk kontrol ke dokter.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('permits.index'));

    $permit = $student->permits()->sole();

    expect($permit->type)->toBe(PermitType::Keluar)
        ->and($permit->status)->toBe(PermitStatus::Approved)
        ->and($permit->decided_by)->toBe($piket->id)
        ->and($permit->decided_at)->not->toBeNull()
        ->and($permit->decision_note)->toContain($piket->name)
        ->and($permit->isAwaitingHomeroom())->toBeTrue();
});

test('a manual permit may be backdated and keeps a custom approval note', function () {
    $student = Student::factory()->create();

    $this->actingAs(adminUser());

    Livewire::test('pages::permit.manual')
        ->set('student_id', $student->id)
        ->set('type', PermitType::PulangAwal->value)
        ->set('date', now()->subDays(2)->toDateString())
        ->set('reason', 'Ban motor bocor, sudah dikonfirmasi wali kelas.')
        ->set('note', 'Sudah dikonfirmasi wali kelas.')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->permits()->sole())
        ->date->toDateString()->toBe(now()->subDays(2)->toDateString())
        ->decision_note->toBe('Sudah dikonfirmasi wali kelas.');
});

test('a manual permit cannot duplicate a live request of the same type and date', function () {
    $student = Student::factory()->create();
    Permit::factory()->for($student)->ofType(PermitType::PulangAwal)->create(['date' => now()->toDateString()]);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::permit.manual')
        ->set('student_id', $student->id)
        ->set('type', PermitType::PulangAwal->value)
        ->set('date', now()->toDateString())
        ->set('reason', 'Dijemput lebih awal.')
        ->call('save')
        ->assertHasErrors('type');

    expect($student->permits()->count())->toBe(1);
});

test('roles without kelola perizinan cannot open the manual input page', function () {
    [$user] = siswaWithStudent();

    $this->actingAs($user)
        ->get(route('permits.manual'))
        ->assertForbidden();

    $this->actingAs(userWithRole(UserRole::WaliKelas))
        ->get(route('permits.manual'))
        ->assertForbidden();

    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('permits.manual'))
        ->assertOk();
});

test('a piket approval puts the permit into effect while it waits for the wali kelas', function () {
    [, $student] = siswaWithStudent();
    $permit = Permit::factory()->for($student)->ofType(PermitType::PulangAwal)->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::permit.show', ['permit' => $permit])->call('approve');

    $permit->refresh();
    expect($permit->status)->toBe(PermitStatus::Approved)
        ->and($permit->statusLabel())->toBe('Menunggu Wali Kelas')
        ->and(Permit::approvedFor($student, PermitType::PulangAwal, now()))->toBeTrue();
});

test('the wali kelas completes a permit the guru piket already approved', function () {
    [, $student] = siswaWithStudent();
    $permit = Permit::factory()->for($student)->approved()->create();
    $walas = walasFor($student);

    $this->actingAs($walas);

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('approve')
        ->assertRedirect(route('permits.index'));

    $permit->refresh();
    expect($permit->isFullyApproved())->toBeTrue()
        ->and($permit->homeroom_approved_by)->toBe($walas->id)
        ->and($permit->statusLabel())->toBe(PermitStatus::Approved->label());

    // Nothing is left to decide once both have signed off.
    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->set('note', 'Berubah pikiran.')
        ->call('reject')
        ->assertStatus(403);

    expect($permit->fresh()->isFullyApproved())->toBeTrue();
});

test('the wali kelas can reject a permit the guru piket already approved', function () {
    [, $student] = siswaWithStudent();
    $permit = Permit::factory()->for($student)->ofType(PermitType::Terlambat)->approved()->create();
    $walas = walasFor($student);

    $this->actingAs($walas);

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->set('note', 'Siswa tidak memberi kabar ke wali kelas.')
        ->call('reject')
        ->assertHasNoErrors();

    $permit->refresh();
    expect($permit->status)->toBe(PermitStatus::Rejected)
        ->and($permit->decided_by)->toBe($walas->id)
        ->and(Permit::approvedFor($student, PermitType::Terlambat, now()))->toBeFalse();
});

test('the guru piket cannot decide again once they approved', function () {
    $permit = Permit::factory()->approved()->create();

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->set('note', 'Salah input.')
        ->call('reject')
        ->assertStatus(403);

    expect($permit->fresh()->status)->toBe(PermitStatus::Approved);
});

test('the permit index lists both approvers and counts what the wali kelas still has to approve', function () {
    [, $student] = siswaWithStudent();
    $walas = walasFor($student);
    $piket = User::factory()->create(['name' => 'Bu Piket']);

    Permit::factory()->for($student)->create(['date' => now()->subDay()->toDateString()]);
    Permit::factory()->for($student)->approved()->create(['decided_by' => $piket->id]);
    Permit::factory()->for($student)->approved()->homeroomApproved()->create(['date' => now()->subDays(2)->toDateString()]);
    Permit::factory()->for($student)->rejected()->create(['date' => now()->subDays(3)->toDateString()]);

    $this->actingAs($walas);

    Livewire::test('pages::permit.index')
        ->assertSee('Bu Piket')
        ->assertSee('Menunggu Wali Kelas')
        ->assertSee('2 pengajuan menunggu persetujuan');
});

test('permits a wali kelas approved before the two-step flow keep that approval', function () {
    [, $student] = siswaWithStudent();
    $walas = walasFor($student);
    $byWalas = Permit::factory()->for($student)->approved()->create(['decided_by' => $walas->id]);
    $byPiket = Permit::factory()->for($student)->approved()->create(['date' => now()->subDay()->toDateString()]);

    $migration = require database_path('migrations/2026_09_29_170352_add_homeroom_approval_to_permits_table.php');
    $migration->down();
    $migration->up();

    expect($byWalas->fresh()->homeroom_approved_by)->toBe($walas->id)
        ->and($byWalas->fresh()->isFullyApproved())->toBeTrue()
        ->and($byPiket->fresh()->isAwaitingHomeroom())->toBeTrue();
});

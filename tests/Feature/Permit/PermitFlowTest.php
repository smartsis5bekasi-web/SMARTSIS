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
        ->set('type', PermitType::Terlambat->value)
        ->set('date', now()->toDateString())
        ->set('reason', 'Antar adik berobat ke puskesmas.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('permits.index'));

    $permit = $student->permits()->sole();

    expect($permit->type)->toBe(PermitType::Terlambat)
        ->and($permit->status)->toBe(PermitStatus::Pending);
});

test('a duplicate request for the same type and date is rejected', function () {
    [$user, $student] = siswaWithStudent();
    Permit::factory()->for($student)->ofType(PermitType::Terlambat)->create(['date' => now()->toDateString()]);

    $this->actingAs($user);

    Livewire::test('pages::permit.create')
        ->set('type', PermitType::Terlambat->value)
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

test('a wali kelas can decide permits of their homeroom students only', function () {
    [, $student] = siswaWithStudent();
    $permit = Permit::factory()->for($student)->create();
    $walas = walasFor($student);

    $this->actingAs($walas);

    Livewire::test('pages::permit.show', ['permit' => $permit])
        ->call('approve');

    expect($permit->fresh()->status)->toBe(PermitStatus::Approved);

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

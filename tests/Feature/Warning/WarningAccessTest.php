<?php

use App\Enums\UserRole;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\WarningLetter;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('viewer roles can open the warning letter index', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('warnings.index'))
        ->assertOk();
})->with([
    'kepala sekolah' => [UserRole::KepalaSekolah],
    'wakasek kesiswaan' => [UserRole::WakasekKesiswaan],
    'guru bk' => [UserRole::GuruBk],
    'wali kelas' => [UserRole::WaliKelas],
]);

test('guru piket and guru mapel have no access to warning letters', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('warnings.index'))
        ->assertForbidden();
})->with([
    'guru piket' => [UserRole::GuruPiket],
    'guru mapel' => [UserRole::GuruMapel],
]);

test('only warning managers can open the settings page', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('warnings.settings'))
        ->assertOk();

    $this->actingAs(userWithRole(UserRole::KepalaSekolah))
        ->get(route('warnings.settings'))
        ->assertForbidden();
});

test('a siswa only sees letters that were actually issued', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $user->id]);

    $pending = WarningLetter::factory()->for($student)->create();
    $issued = WarningLetter::factory()->for($student)->approved()->create();

    $this->actingAs($user);

    // The pending recommendation is internal — not even viewable.
    Livewire::test('pages::warning.show', ['warningLetter' => $pending])
        ->assertStatus(403);

    $this->get(route('warnings.show', $issued))->assertOk();
});

test('a viewer role cannot decide a recommendation', function () {
    $letter = WarningLetter::factory()->create();

    $this->actingAs(userWithRole(UserRole::WakasekKesiswaan));

    Livewire::test('pages::warning.show', ['warningLetter' => $letter])
        ->call('approve')
        ->assertStatus(403);

    expect($letter->fresh()->isPending())->toBeTrue();
});

test('an orang tua can view and print their child\'s issued letter only', function () {
    $ortu = userWithRole(UserRole::OrangTua);
    $parent = ParentGuardian::factory()->create(['user_id' => $ortu->id]);
    $student = Student::factory()->create();
    $parent->students()->attach($student->id, ['relationship' => 'Ayah']);

    $issued = WarningLetter::factory()->for($student)->approved()->create();
    $othersLetter = WarningLetter::factory()->approved()->create();

    $this->actingAs($ortu)
        ->get(route('warnings.print', $issued))
        ->assertOk();

    $this->get(route('warnings.print', $othersLetter))->assertForbidden();
});

test('a pending recommendation has no printable letter', function () {
    $pending = WarningLetter::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('warnings.print', $pending))
        ->assertNotFound();
});

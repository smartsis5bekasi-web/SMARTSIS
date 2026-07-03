<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Violation;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests are redirected from the violation page', function () {
    $this->get(route('academic.violations'))->assertRedirect(route('login'));
});

test('guru piket can open the recording form', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('academic.violations.create'))
        ->assertOk();
});

test('guru bk can open the recording form', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('academic.violations.create'))
        ->assertOk();
});

test('a view-only role cannot open the recording form', function () {
    $this->actingAs(userWithRole(UserRole::WaliKelas))
        ->get(route('academic.violations.create'))
        ->assertForbidden();
});

test('a student cannot open the recording form', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('academic.violations.create'))
        ->assertForbidden();
});

test('a student cannot view another students violation', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id]);
    $other = Violation::factory()->create();

    $this->actingAs($user)
        ->get(route('academic.violations.show', $other))
        ->assertForbidden();
});

test('a student cannot approve a violation', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $violation = Violation::factory()->create(['student_id' => $student->id]);

    $this->actingAs($user);

    Livewire::test('pages::academic.violation.show', ['violation' => $violation])
        ->call('approve')
        ->assertForbidden();

    expect($violation->fresh()->status->value)->toBe('pending');
});

test('guru piket cannot verify a violation', function () {
    $piket = userWithRole(UserRole::GuruPiket);
    $violation = Violation::factory()->create();

    $this->actingAs($piket);

    Livewire::test('pages::academic.violation.show', ['violation' => $violation])
        ->call('approve')
        ->assertForbidden();

    expect($violation->fresh()->status->value)->toBe('pending');
});

test('an approved violation cannot be edited', function () {
    $violation = Violation::factory()->approved()->create();

    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('academic.violations.edit', $violation))
        ->assertForbidden();
});

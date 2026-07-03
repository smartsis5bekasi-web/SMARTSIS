<?php

use App\Enums\UserRole;
use App\Models\Achievement;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests are redirected from the achievement page', function () {
    $this->get(route('academic.achievements'))->assertRedirect(route('login'));
});

test('a student can open the submission form', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('academic.achievements.create'))->assertOk();
});

test('guru bk can open the input form', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('academic.achievements.create'))
        ->assertOk();
});

test('a view-only role cannot open the submission form', function () {
    $this->actingAs(userWithRole(UserRole::WaliKelas))
        ->get(route('academic.achievements.create'))
        ->assertForbidden();
});

test('a student cannot view another students achievement', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id]);
    $other = Achievement::factory()->create();

    $this->actingAs($user)
        ->get(route('academic.achievements.show', $other))
        ->assertForbidden();
});

test('a student cannot approve an achievement', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $achievement = Achievement::factory()->create(['student_id' => $student->id]);

    $this->actingAs($user);

    Livewire::test('pages::academic.achievement.show', ['achievement' => $achievement])
        ->call('approve')
        ->assertForbidden();

    expect($achievement->fresh()->status->value)->toBe('pending');
});

test('an approved achievement cannot be edited', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $user->id]);
    $achievement = Achievement::factory()->approved()->create(['student_id' => $student->id]);

    $this->actingAs($user)
        ->get(route('academic.achievements.edit', $achievement))
        ->assertForbidden();
});

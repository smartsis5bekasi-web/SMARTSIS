<?php

use App\Enums\UserRole;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a student can view their own development point page', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('attendance.points.show', $student))
        ->assertOk();
});

test('a student cannot view another students development point', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id]);
    $other = Student::factory()->create();

    $this->actingAs($user)
        ->get(route('attendance.points.show', $other))
        ->assertForbidden();
});

test('the point menu redirects a student to their own detail page', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('attendance.points'))
        ->assertRedirect(route('attendance.points.show', $student));
});

test('a manager can view any students development point', function () {
    $student = Student::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruBk))
        ->get(route('attendance.points.show', $student))
        ->assertOk();
});

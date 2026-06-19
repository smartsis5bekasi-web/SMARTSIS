<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

test('user is active by default and can be deactivated', function () {
    expect(User::factory()->create()->is_active)->toBeTrue()
        ->and(User::factory()->inactive()->create()->is_active)->toBeFalse();
});

test('a teacher belongs to a user account', function () {
    $teacher = Teacher::factory()->create();

    expect($teacher->user)->toBeInstanceOf(User::class)
        ->and($teacher->user->teacher->is($teacher))->toBeTrue();
});

test('a student can be linked to a user account', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    expect($student->user->is($user))->toBeTrue()
        ->and($user->student->is($student))->toBeTrue();
});

test('a parent monitors students through the parent_student pivot', function () {
    $parent = ParentGuardian::factory()->create();
    $student = Student::factory()->create();

    $parent->students()->attach($student, ['relationship' => 'Ibu']);

    expect($parent->students)->toHaveCount(1)
        ->and($parent->students->first()->is($student))->toBeTrue()
        ->and($student->parents)->toHaveCount(1);
});

test('database seeder wires every demo account to its role and profile', function () {
    $this->seed();

    $admin = User::where('email', UserRole::SuperAdmin->value.'@smartsis.test')->first();
    expect($admin?->hasRole(UserRole::SuperAdmin->value))->toBeTrue();

    $siswa = User::where('email', UserRole::Siswa->value.'@smartsis.test')->first();
    expect($siswa?->hasRole(UserRole::Siswa->value))->toBeTrue()
        ->and($siswa?->student)->not->toBeNull();

    $ortu = User::where('email', UserRole::OrangTua->value.'@smartsis.test')->first();
    expect($ortu?->parentGuardian)->not->toBeNull()
        ->and($ortu?->parentGuardian->students)->toHaveCount(1);

    $wali = User::where('email', UserRole::WaliKelas->value.'@smartsis.test')->first();
    expect(Classroom::where('homeroom_teacher_id', $wali?->teacher->id)->exists())->toBeTrue();
});

<?php

use App\Enums\PointApprovalStatus;
use App\Enums\UserRole;
use App\Models\Achievement;
use App\Models\PointRule;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('public');
});

test('a student submits an achievement that stays pending without points', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id, 'current_point' => 100]);
    $rule = PointRule::factory()->addition()->create(['point' => 20]);

    $this->actingAs($user);

    Livewire::test('pages::academic.achievement.create')
        ->set('point_rule_id', $rule->id)
        ->set('title', 'Juara 1 LKS')
        ->set('level', 'Provinsi')
        ->set('achieved_on', now()->subDay()->toDateString())
        ->set('evidence', UploadedFile::fake()->create('bukti.pdf', 200, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('academic.achievements'));

    $this->assertDatabaseHas('achievements', [
        'student_id' => $student->id,
        'title' => 'Juara 1 LKS',
        'status' => PointApprovalStatus::Pending->value,
        'input_by' => $user->id,
    ]);
    expect($student->fresh()->current_point)->toBe(100);
});

test('a student submission requires evidence', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->create(['user_id' => $user->id]);
    $rule = PointRule::factory()->addition()->create();

    $this->actingAs($user);

    Livewire::test('pages::academic.achievement.create')
        ->set('point_rule_id', $rule->id)
        ->set('title', 'Tanpa Bukti')
        ->set('level', 'Sekolah')
        ->set('achieved_on', now()->subDay()->toDateString())
        ->call('save')
        ->assertHasErrors('evidence');
});

test('staff input is auto-approved and applies points immediately', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->addition()->create(['point' => 20]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.achievement.create')
        ->set('student_id', $student->id)
        ->set('point_rule_id', $rule->id)
        ->set('title', 'Juara Olimpiade')
        ->set('level', 'Nasional')
        ->set('achieved_on', now()->subDay()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('achievements', [
        'title' => 'Juara Olimpiade',
        'status' => PointApprovalStatus::Approved->value,
    ]);
    expect($student->fresh()->current_point)->toBe(120);
});

test('a manager approves a pending submission and points are applied', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 50]);
    $rule = PointRule::factory()->addition()->create(['point' => 20]);
    $achievement = Achievement::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.achievement.show', ['achievement' => $achievement])
        ->call('approve')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(70)
        ->and($achievement->fresh()->status)->toBe(PointApprovalStatus::Approved);
});

test('the approver can correct the jenis prestasi before approving', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 50]);
    $wrong = PointRule::factory()->addition()->create(['point' => 10]);
    $correct = PointRule::factory()->addition()->create(['point' => 50]);
    $achievement = Achievement::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $wrong->id,
    ]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.achievement.show', ['achievement' => $achievement])
        ->set('point_rule_id', $correct->id)
        ->call('approve')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(100)
        ->and($achievement->fresh()->point_rule_id)->toBe($correct->id);
});

test('a manager rejects a submission with a reason and no points change', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $achievement = Achievement::factory()->create(['student_id' => $student->id]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.achievement.show', ['achievement' => $achievement])
        ->set('note', 'Bukti tidak valid')
        ->call('reject')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(100)
        ->and($achievement->fresh()->status)->toBe(PointApprovalStatus::Rejected)
        ->and($achievement->fresh()->note)->toBe('Bukti tidak valid');
});

test('rejection requires a reason', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $achievement = Achievement::factory()->create();

    $this->actingAs($bk);

    Livewire::test('pages::academic.achievement.show', ['achievement' => $achievement])
        ->call('reject')
        ->assertHasErrors('note');
});

test('deleting an approved achievement reverses its points', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->addition()->create(['point' => 20]);
    $achievement = Achievement::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);
    $achievement->approve($bk);
    expect($student->fresh()->current_point)->toBe(120);

    $this->actingAs($bk);

    Livewire::test('pages::academic.achievement.show', ['achievement' => $achievement->fresh()])
        ->call('delete')
        ->assertRedirect(route('academic.achievements'));

    expect($student->fresh()->current_point)->toBe(100);
    $this->assertDatabaseMissing('achievements', ['id' => $achievement->id]);
});

test('a student can edit their own pending submission', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $rule = PointRule::factory()->addition()->create();
    $achievement = Achievement::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::academic.achievement.edit', ['achievement' => $achievement])
        ->set('title', 'Judul Diperbarui')
        ->call('save')
        ->assertHasNoErrors();

    expect($achievement->fresh()->title)->toBe('Judul Diperbarui');
});

<?php

use App\Enums\PointApprovalStatus;
use App\Enums\UserRole;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Violation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('public');
});

test('guru piket records a violation that stays pending without points', function () {
    $piket = userWithRole(UserRole::GuruPiket);
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);

    $this->actingAs($piket);

    Livewire::test('pages::academic.violation.create')
        ->set('student_id', $student->id)
        ->set('point_rule_id', $rule->id)
        ->set('occurred_on', now()->subDay()->toDateString())
        ->set('chronology', 'Merokok di belakang sekolah')
        ->set('evidence', UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('academic.violations'));

    $this->assertDatabaseHas('violations', [
        'student_id' => $student->id,
        'status' => PointApprovalStatus::Pending->value,
        'reported_by' => $piket->id,
    ]);
    expect($student->fresh()->current_point)->toBe(100);
});

test('guru bk recording is auto-approved and deducts points immediately', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.violation.create')
        ->set('student_id', $student->id)
        ->set('point_rule_id', $rule->id)
        ->set('occurred_on', now()->subDay()->toDateString())
        ->set('chronology', 'Terlambat berulang')
        ->set('evidence', UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('violations', [
        'student_id' => $student->id,
        'status' => PointApprovalStatus::Approved->value,
    ]);
    expect($student->fresh()->current_point)->toBe(70);
});

test('recording a violation requires evidence', function () {
    $piket = userWithRole(UserRole::GuruPiket);
    $student = Student::factory()->create();
    $rule = PointRule::factory()->deduction()->create();

    $this->actingAs($piket);

    Livewire::test('pages::academic.violation.create')
        ->set('student_id', $student->id)
        ->set('point_rule_id', $rule->id)
        ->set('occurred_on', now()->subDay()->toDateString())
        ->set('chronology', 'Tanpa bukti')
        ->call('save')
        ->assertHasErrors('evidence');
});

test('guru bk approves a pending violation and points are deducted', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);
    $violation = Violation::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.violation.show', ['violation' => $violation])
        ->call('approve')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(70)
        ->and($violation->fresh()->status)->toBe(PointApprovalStatus::Approved);
});

test('the approver can correct the jenis pelanggaran before approving', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $wrong = PointRule::factory()->deduction()->create(['point' => 10]);
    $correct = PointRule::factory()->deduction()->create(['point' => 30]);
    $violation = Violation::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $wrong->id,
    ]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.violation.show', ['violation' => $violation])
        ->set('point_rule_id', $correct->id)
        ->call('approve')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(70)
        ->and($violation->fresh()->point_rule_id)->toBe($correct->id);
});

test('guru bk rejects a violation with a reason and no points change', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $violation = Violation::factory()->create(['student_id' => $student->id]);

    $this->actingAs($bk);

    Livewire::test('pages::academic.violation.show', ['violation' => $violation])
        ->set('note', 'Bukti tidak valid')
        ->call('reject')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(100)
        ->and($violation->fresh()->status)->toBe(PointApprovalStatus::Rejected)
        ->and($violation->fresh()->note)->toBe('Bukti tidak valid');
});

test('rejection requires a reason', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $violation = Violation::factory()->create();

    $this->actingAs($bk);

    Livewire::test('pages::academic.violation.show', ['violation' => $violation])
        ->call('reject')
        ->assertHasErrors('note');
});

test('deleting an approved violation reverses its points', function () {
    $bk = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);
    $violation = Violation::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);
    $violation->approve($bk);
    expect($student->fresh()->current_point)->toBe(70);

    $this->actingAs($bk);

    Livewire::test('pages::academic.violation.show', ['violation' => $violation->fresh()])
        ->call('delete')
        ->assertRedirect(route('academic.violations'));

    expect($student->fresh()->current_point)->toBe(100);
    $this->assertDatabaseMissing('violations', ['id' => $violation->id]);
});

test('guru piket can edit their own pending violation', function () {
    $piket = userWithRole(UserRole::GuruPiket);
    $rule = PointRule::factory()->deduction()->create();
    $violation = Violation::factory()->create([
        'point_rule_id' => $rule->id,
        'reported_by' => $piket->id,
    ]);

    $this->actingAs($piket);

    Livewire::test('pages::academic.violation.edit', ['violation' => $violation])
        ->set('chronology', 'Kronologi diperbarui')
        ->call('save')
        ->assertHasNoErrors();

    expect($violation->fresh()->chronology)->toBe('Kronologi diperbarui');
});

test('guru piket cannot edit a violation reported by someone else', function () {
    $piket = userWithRole(UserRole::GuruPiket);
    $violation = Violation::factory()->create(['reported_by' => userWithRole(UserRole::GuruBk)->id]);

    $this->actingAs($piket)
        ->get(route('academic.violations.edit', $violation))
        ->assertForbidden();
});

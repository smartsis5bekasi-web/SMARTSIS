<?php

use App\Enums\PointApprovalStatus;
use App\Models\Achievement;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;

test('approving a violation deducts points and logs the source', function () {
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);
    $violation = Violation::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);

    $violation->approve(User::factory()->create());

    expect($student->fresh()->current_point)->toBe(70)
        ->and($violation->fresh()->status)->toBe(PointApprovalStatus::Approved);

    $this->assertDatabaseHas('point_logs', [
        'student_id' => $student->id,
        'source_type' => $violation->getMorphClass(),
        'source_id' => $violation->id,
        'delta' => -30,
    ]);
});

test('approving an achievement adds points and logs the source', function () {
    $student = Student::factory()->create(['current_point' => 50]);
    $rule = PointRule::factory()->addition()->create(['point' => 20]);
    $achievement = Achievement::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);

    $achievement->approve(User::factory()->create());

    expect($student->fresh()->current_point)->toBe(70);

    $this->assertDatabaseHas('point_logs', [
        'source_type' => $achievement->getMorphClass(),
        'source_id' => $achievement->id,
        'delta' => 20,
    ]);
});

test('rejecting a violation leaves points untouched', function () {
    $student = Student::factory()->create(['current_point' => 100]);
    $violation = Violation::factory()->create(['student_id' => $student->id]);

    $violation->reject(User::factory()->create());

    expect($student->fresh()->current_point)->toBe(100)
        ->and($violation->fresh()->status)->toBe(PointApprovalStatus::Rejected);
    $this->assertDatabaseCount('point_logs', 0);
});

test('re-approving an already approved violation does not double-apply', function () {
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);
    $violation = Violation::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);

    $verifier = User::factory()->create();
    $violation->approve($verifier);
    $violation->fresh()->approve($verifier);

    expect($student->fresh()->current_point)->toBe(70);
    $this->assertDatabaseCount('point_logs', 1);
});

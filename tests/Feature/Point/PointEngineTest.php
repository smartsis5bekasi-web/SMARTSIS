<?php

use App\Actions\Point\ApplyPointAdjustment;
use App\Models\PointRule;
use App\Models\Student;

test('an addition rule increases the balance and records a log', function () {
    $student = Student::factory()->create(['current_point' => 50]);
    $rule = PointRule::factory()->addition()->create(['point' => 20]);

    $log = app(ApplyPointAdjustment::class)->handle($student, $rule);

    expect($student->fresh()->current_point)->toBe(70)
        ->and($log->delta)->toBe(20)
        ->and($log->balance_after)->toBe(70);

    $this->assertDatabaseHas('point_logs', [
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
        'delta' => 20,
        'balance_after' => 70,
    ]);
});

test('a deduction rule decreases the balance', function () {
    $student = Student::factory()->create(['current_point' => 50]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);

    app(ApplyPointAdjustment::class)->handle($student, $rule);

    expect($student->fresh()->current_point)->toBe(20);
});

test('the balance is clamped at zero', function () {
    $student = Student::factory()->create(['current_point' => 10]);
    $rule = PointRule::factory()->deduction()->create(['point' => 30]);

    $log = app(ApplyPointAdjustment::class)->handle($student, $rule);

    expect($student->fresh()->current_point)->toBe(0)
        ->and($log->delta)->toBe(-30)
        ->and($log->balance_after)->toBe(0);
});

test('reverse restores the balance and writes a compensating log', function () {
    $student = Student::factory()->create(['current_point' => 50]);
    $rule = PointRule::factory()->deduction()->create(['point' => 20]);
    $engine = app(ApplyPointAdjustment::class);

    $log = $engine->handle($student, $rule);
    expect($student->fresh()->current_point)->toBe(30);

    $engine->reverse($log->fresh());

    expect($student->fresh()->current_point)->toBe(50);
    $this->assertDatabaseCount('point_logs', 2);
});

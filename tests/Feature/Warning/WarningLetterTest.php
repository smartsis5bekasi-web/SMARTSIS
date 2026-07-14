<?php

use App\Actions\Point\ApplyPointAdjustment;
use App\Actions\Warning\EvaluateWarningRecommendation;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Enums\WarningLevel;
use App\Enums\WarningStatus;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Violation;
use App\Models\WarningLetter;
use App\Models\WarningSetting;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    WarningSetting::current(); // defaults: SP1 ≤80, SP2 ≤60, SP3 ≤40
});

/**
 * Deduct points from the student through the real point engine.
 */
function deductPoints(Student $student, int $points): void
{
    $rule = PointRule::factory()->create([
        'type' => PointType::Deduction,
        'source' => PointSource::Manual,
        'point' => $points,
    ]);

    app(ApplyPointAdjustment::class)->handle($student, $rule);
}

test('a recommendation is created automatically when points cross a threshold', function () {
    $student = Student::factory()->create(['current_point' => 100]);

    deductPoints($student, 25); // 100 → 75, qualifies SP1 (≤80)

    $letter = $student->warningLetters()->sole();

    expect($letter->level)->toBe(WarningLevel::Sp1)
        ->and($letter->status)->toBe(WarningStatus::Pending)
        ->and($letter->points_snapshot)->toBe(75)
        ->and($letter->threshold)->toBe(80);
});

test('approving a violation can trigger the recommendation end-to-end', function () {
    $student = Student::factory()->create(['current_point' => 100]);
    $rule = PointRule::factory()->create([
        'type' => PointType::Deduction,
        'source' => PointSource::Violation,
        'point' => 45,
    ]);
    $violation = Violation::factory()->create(['student_id' => $student->id, 'point_rule_id' => $rule->id]);

    $violation->approve(userWithRole(UserRole::GuruBk));

    // 100 → 55 lands below the SP2 threshold (≤60), skipping SP1.
    $letter = $student->warningLetters()->sole();

    expect($letter->level)->toBe(WarningLevel::Sp2)
        ->and($letter->points_snapshot)->toBe(55);
});

test('no recommendation is created while points stay above every threshold', function () {
    $student = Student::factory()->create(['current_point' => 100]);

    deductPoints($student, 5); // 95, above SP1

    expect(WarningLetter::query()->count())->toBe(0);
});

test('an existing pending recommendation is not duplicated', function () {
    $student = Student::factory()->create(['current_point' => 80]);

    deductPoints($student, 2); // 78 → SP1 recommended
    deductPoints($student, 2); // 76 → still SP1, already pending

    expect($student->warningLetters()->count())->toBe(1);
});

test('falling further escalates to the next level', function () {
    $student = Student::factory()->create(['current_point' => 80]);

    deductPoints($student, 2);  // 78 → SP1
    deductPoints($student, 20); // 58 → SP2

    expect($student->warningLetters()->pluck('level')->all())
        ->toEqualCanonicalizing([WarningLevel::Sp1, WarningLevel::Sp2]);
});

test('guru bk approval issues the letter with a sequential number', function () {
    $letter = WarningLetter::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::warning.show', ['warningLetter' => $letter])
        ->call('approve');

    $letter->refresh();

    expect($letter->status)->toBe(WarningStatus::Approved)
        ->and($letter->letter_number)->toMatch('/^\d{3}\/SP1\/[IVX]+\/\d{4}$/')
        ->and($letter->decided_at)->not->toBeNull();

    // The next issued letter continues the yearly sequence.
    $second = WarningLetter::factory()->create();
    $second->approve(userWithRole(UserRole::GuruBk));

    expect((int) substr($second->fresh()->letter_number, 0, 3))
        ->toBe((int) substr($letter->letter_number, 0, 3) + 1);
});

test('rejecting a recommendation requires a note', function () {
    $letter = WarningLetter::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruBk));

    $component = Livewire::test('pages::warning.show', ['warningLetter' => $letter])
        ->call('reject')
        ->assertHasErrors('note');

    expect($letter->fresh()->isPending())->toBeTrue();

    $component->set('note', 'Poin sudah dikoreksi, data awal keliru.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($letter->fresh()->status)->toBe(WarningStatus::Rejected);
});

test('a decided letter can never be re-decided', function () {
    $letter = WarningLetter::factory()->approved()->create();
    $number = $letter->letter_number;

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::warning.show', ['warningLetter' => $letter])
        ->call('approve')
        ->assertStatus(403);

    expect($letter->fresh()->letter_number)->toBe($number);
});

test('changing the thresholds re-evaluates every student', function () {
    $student = Student::factory()->create(['current_point' => 85]); // above default SP1

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::warning.settings')
        ->set('sp1_threshold', 90)
        ->set('sp2_threshold', 60)
        ->set('sp3_threshold', 40)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('warnings.index'));

    $letter = $student->warningLetters()->sole();

    expect($letter->level)->toBe(WarningLevel::Sp1)
        ->and($letter->threshold)->toBe(90);
});

test('the thresholds must be strictly descending', function () {
    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::warning.settings')
        ->set('sp1_threshold', 60)
        ->set('sp2_threshold', 80)
        ->set('sp3_threshold', 40)
        ->call('save')
        ->assertHasErrors('sp2_threshold');
});

test('the manual sweep reports newly found recommendations', function () {
    Student::factory()->create(['current_point' => 70]);
    Student::factory()->create(['current_point' => 95]);

    expect(app(EvaluateWarningRecommendation::class)->sweep())->toBe(1)
        ->and(WarningLetter::query()->count())->toBe(1);

    // Running it again finds nothing new.
    expect(app(EvaluateWarningRecommendation::class)->sweep())->toBe(0);
});

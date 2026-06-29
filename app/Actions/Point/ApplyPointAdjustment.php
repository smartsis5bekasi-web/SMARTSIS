<?php

namespace App\Actions\Point;

use App\Enums\PointType;
use App\Models\PointLog;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single entry point for every student point change (PRD section 6.1 —
 * Dynamic Point Engine). Updates the student's balance and writes an immutable
 * {@see PointLog} entry, all inside a row-locked transaction so concurrent
 * adjustments cannot corrupt the running balance.
 */
class ApplyPointAdjustment
{
    /**
     * Apply a rule's point delta to a student and record the change.
     *
     * @param  Model|null  $source  the record that triggered the change
     */
    public function handle(
        Student $student,
        PointRule $rule,
        ?Model $source = null,
        ?string $note = null,
        ?User $by = null,
    ): PointLog {
        return DB::transaction(function () use ($student, $rule, $source, $note, $by): PointLog {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);

            $delta = $rule->signedPoint();
            $balance = max(0, $student->current_point + $delta);

            $student->update(['current_point' => $balance]);

            return $this->log($student, $rule->type, $delta, $balance, [
                'point_rule_id' => $rule->id,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'note' => $note,
                'created_by' => $by?->id,
            ]);
        });
    }

    /**
     * Reverse a previously applied adjustment (e.g. an approval was revoked),
     * writing a compensating log entry so the audit trail stays intact.
     */
    public function reverse(PointLog $log, ?User $by = null, ?string $note = null): PointLog
    {
        return DB::transaction(function () use ($log, $by, $note): PointLog {
            $student = Student::query()->lockForUpdate()->findOrFail($log->student_id);

            $delta = -$log->delta;
            $balance = max(0, $student->current_point + $delta);

            $student->update(['current_point' => $balance]);

            $type = $delta >= 0 ? PointType::Addition : PointType::Deduction;

            return $this->log($student, $type, $delta, $balance, [
                'point_rule_id' => $log->point_rule_id,
                'source_type' => $log->source_type,
                'source_id' => $log->source_id,
                'note' => $note ?? __('Pembatalan: :note', ['note' => $log->note]),
                'created_by' => $by?->id,
            ]);
        });
    }

    /**
     * Persist a point log entry for the given student.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function log(Student $student, PointType $type, int $delta, int $balance, array $attributes): PointLog
    {
        return $student->pointLogs()->create([
            'type' => $type,
            'delta' => $delta,
            'balance_after' => $balance,
            ...$attributes,
        ]);
    }
}

<?php

namespace App\Actions\Warning;

use App\Enums\WarningStatus;
use App\Models\Student;
use App\Models\WarningLetter;
use App\Models\WarningSetting;

/**
 * Detects students whose point balance qualifies for a warning letter and
 * creates the pending recommendation for Guru BK (PRD F-21). Runs after every
 * point change and on demand from the SP page ("Periksa Rekomendasi").
 */
class EvaluateWarningRecommendation
{
    /**
     * Create a recommendation for the student's most severe qualifying level.
     *
     * Skipped when a recommendation or issued letter at that level (or a more
     * severe one) already exists, so repeated point drops escalate instead of
     * spamming duplicates. A rejected letter does not block a new
     * recommendation — the points kept falling, so BK should look again.
     */
    public function evaluate(Student $student): ?WarningLetter
    {
        $setting = WarningSetting::current();
        $level = $setting->levelFor($student->current_point);

        if ($level === null) {
            return null;
        }

        $alreadyCovered = $student->warningLetters()
            ->where('level', '>=', $level->value)
            ->whereIn('status', [WarningStatus::Pending, WarningStatus::Approved])
            ->exists();

        if ($alreadyCovered) {
            return null;
        }

        return $student->warningLetters()->create([
            'level' => $level,
            'status' => WarningStatus::Pending,
            'points_snapshot' => $student->current_point,
            'threshold' => $setting->thresholdFor($level),
        ]);
    }

    /**
     * Re-check every student (used after the thresholds change).
     *
     * @return int the number of new recommendations created
     */
    public function sweep(): int
    {
        $created = 0;

        Student::query()->chunkById(100, function ($students) use (&$created): void {
            foreach ($students as $student) {
                if ($this->evaluate($student) !== null) {
                    $created++;
                }
            }
        });

        return $created;
    }
}

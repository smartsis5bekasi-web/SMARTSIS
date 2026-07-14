<?php

namespace App\Events;

use App\Models\PointLog;
use App\Models\Student;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised after every point balance mutation (adjustment or reversal) so
 * downstream modules — the Warning Letter recommender today, the Smart
 * Recommendation Engine later — can react to the fresh balance (PRD F-21).
 */
class PointBalanceChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Student $student,
        public PointLog $log,
    ) {}
}

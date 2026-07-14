<?php

namespace App\Listeners;

use App\Actions\Warning\EvaluateWarningRecommendation;
use App\Events\PointBalanceChanged;

/**
 * Evaluates the SP thresholds whenever a student's balance changes, so
 * recommendations appear for Guru BK the moment points fall low enough
 * (PRD 7.7 — monitor poin → rekomendasi → verifikasi BK).
 */
class RecommendWarningLetter
{
    public function __construct(
        private readonly EvaluateWarningRecommendation $engine,
    ) {}

    public function handle(PointBalanceChanged $event): void
    {
        $this->engine->evaluate($event->student);
    }
}

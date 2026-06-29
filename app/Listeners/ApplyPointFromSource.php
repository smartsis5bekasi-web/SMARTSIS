<?php

namespace App\Listeners;

use App\Actions\Point\ApplyPointAdjustment;
use App\Events\Contracts\PointSourceVerified;

/**
 * Applies the point adjustment for any approved point source (violation,
 * achievement, …). One listener serves every source via the
 * {@see PointSourceVerified} contract.
 */
class ApplyPointFromSource
{
    public function __construct(
        private readonly ApplyPointAdjustment $engine,
    ) {}

    public function handle(PointSourceVerified $event): void
    {
        $this->engine->handle(
            student: $event->student(),
            rule: $event->rule(),
            source: $event->source(),
            note: $event->note(),
            by: $event->verifier(),
        );
    }
}

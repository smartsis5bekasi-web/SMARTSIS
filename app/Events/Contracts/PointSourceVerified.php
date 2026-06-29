<?php

namespace App\Events\Contracts;

use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by any "X verified/approved" event whose approval should drive
 * the Dynamic Point Engine. Lets a single listener apply points regardless of
 * which module raised the event (PRD section 6.1 — Event → Listener).
 */
interface PointSourceVerified
{
    /**
     * The record that triggered the point change (violation, achievement, …).
     */
    public function source(): Model;

    /**
     * The student whose balance changes.
     */
    public function student(): Student;

    /**
     * The rule that defines the point delta.
     */
    public function rule(): PointRule;

    /**
     * The user who approved the record (recorded as the author of the log).
     */
    public function verifier(): User;

    /**
     * A human-readable note stored on the point log.
     */
    public function note(): string;
}

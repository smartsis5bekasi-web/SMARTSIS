<?php

namespace App\Events;

use App\Events\Contracts\PointSourceVerified;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when a violation is approved by Guru BK (PRD F-16). Drives the point
 * engine to deduct the rule's points from the student.
 */
class ViolationVerified implements PointSourceVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Violation $violation,
        public User $verifier,
    ) {}

    public function source(): Model
    {
        return $this->violation;
    }

    public function student(): Student
    {
        return $this->violation->student;
    }

    public function rule(): PointRule
    {
        return $this->violation->pointRule;
    }

    public function verifier(): User
    {
        return $this->verifier;
    }

    public function note(): string
    {
        return __('Pelanggaran: :name', ['name' => $this->violation->pointRule->name]);
    }
}

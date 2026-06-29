<?php

namespace App\Events;

use App\Events\Contracts\PointSourceVerified;
use App\Models\Achievement;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when an achievement is approved by Guru BK (PRD F-19). Drives the
 * point engine to add the rule's points to the student.
 */
class AchievementVerified implements PointSourceVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Achievement $achievement,
        public User $verifier,
    ) {}

    public function source(): Model
    {
        return $this->achievement;
    }

    public function student(): Student
    {
        return $this->achievement->student;
    }

    public function rule(): PointRule
    {
        return $this->achievement->pointRule;
    }

    public function verifier(): User
    {
        return $this->verifier;
    }

    public function note(): string
    {
        return __('Prestasi: :name', ['name' => $this->achievement->pointRule->name]);
    }
}

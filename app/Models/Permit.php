<?php

namespace App\Models;

use App\Enums\PermitStatus;
use App\Enums\PermitType;
use Carbon\CarbonInterface;
use Database\Factories\PermitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property PermitType $type
 * @property Carbon $date
 * @property string $reason
 * @property string|null $attachment_path
 * @property PermitStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 */
class Permit extends Model
{
    /** @use HasFactory<PermitFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'type',
        'date',
        'reason',
        'attachment_path',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    /**
     * Whether the permit is still awaiting a decision.
     */
    public function isPending(): bool
    {
        return $this->status === PermitStatus::Pending;
    }

    /**
     * Approve the permit (F-25). Idempotent: a decided permit is never
     * re-decided.
     */
    public function approve(User $decider, ?string $note = null): void
    {
        if (! $this->isPending()) {
            return;
        }

        $this->update([
            'status' => PermitStatus::Approved,
            'decided_by' => $decider->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    /**
     * Reject the permit with a mandatory reason so the student knows why.
     */
    public function reject(User $decider, string $note): void
    {
        if (! $this->isPending()) {
            return;
        }

        $this->update([
            'status' => PermitStatus::Rejected,
            'decided_by' => $decider->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    /**
     * Whether the student holds an approved permit of the given type for the
     * given day — consulted by Smart Attendance (izin terlambat waives the
     * late penalty, izin pulang awal opens check-out early).
     */
    public static function approvedFor(Student $student, PermitType $type, CarbonInterface $date): bool
    {
        return static::query()
            ->where('student_id', $student->id)
            ->where('type', $type)
            ->where('status', PermitStatus::Approved)
            ->whereDate('date', $date->toDateString())
            ->exists();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The user who approved/rejected the permit.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PermitType::class,
            'status' => PermitStatus::class,
            'date' => 'date',
            'decided_at' => 'datetime',
        ];
    }
}

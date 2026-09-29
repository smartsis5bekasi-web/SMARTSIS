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
 * @property int|null $homeroom_approved_by
 * @property Carbon|null $homeroom_approved_at
 * @property string|null $homeroom_note
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
        'homeroom_approved_by',
        'homeroom_approved_at',
        'homeroom_note',
    ];

    /**
     * Whether the permit is still awaiting the Guru Piket's decision.
     */
    public function isPending(): bool
    {
        return $this->status === PermitStatus::Pending;
    }

    /**
     * Whether the student's wali kelas has signed off on the permit.
     */
    public function isHomeroomApproved(): bool
    {
        return $this->homeroom_approved_at !== null;
    }

    /**
     * Whether the permit still needs the wali kelas' approval — true both
     * before and after the Guru Piket decides, until it is rejected.
     */
    public function isAwaitingHomeroom(): bool
    {
        return $this->status !== PermitStatus::Rejected && ! $this->isHomeroomApproved();
    }

    /**
     * Both the Guru Piket and the wali kelas approved the permit.
     */
    public function isFullyApproved(): bool
    {
        return $this->status === PermitStatus::Approved && $this->isHomeroomApproved();
    }

    /**
     * The label shown to users. `status` alone only tracks the Guru Piket
     * decision, so an approved permit reads "Menunggu Wali Kelas" until the
     * wali kelas signs off too.
     */
    public function statusLabel(): string
    {
        return match (true) {
            $this->status === PermitStatus::Rejected => PermitStatus::Rejected->label(),
            $this->isFullyApproved() => PermitStatus::Approved->label(),
            $this->status === PermitStatus::Approved => __('Menunggu Wali Kelas'),
            $this->isHomeroomApproved() => __('Menunggu Guru Piket'),
            default => PermitStatus::Pending->label(),
        };
    }

    /**
     * The Guru Piket's approval (F-25). This is the decision that makes the
     * permit count for attendance ({@see self::approvedFor()}); the wali
     * kelas' sign-off is still required but does not hold the student up.
     * Idempotent: a decided permit is never re-decided.
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
     * The wali kelas' approval, recorded alongside the Guru Piket decision.
     */
    public function approveAsHomeroom(User $teacher, ?string $note = null): void
    {
        if (! $this->isAwaitingHomeroom()) {
            return;
        }

        $this->update([
            'homeroom_approved_by' => $teacher->id,
            'homeroom_approved_at' => now(),
            'homeroom_note' => $note,
        ]);
    }

    /**
     * Reject the permit with a mandatory reason so the student knows why.
     *
     * Either approver may reject until both have approved, so a wali kelas can
     * still turn down a permit the Guru Piket already let through.
     */
    public function reject(User $decider, string $note): void
    {
        if ($this->status === PermitStatus::Rejected || $this->isFullyApproved()) {
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
     * The wali kelas who approved the permit.
     *
     * @return BelongsTo<User, $this>
     */
    public function homeroomApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_approved_by');
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
            'homeroom_approved_at' => 'datetime',
        ];
    }
}

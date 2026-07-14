<?php

namespace App\Models;

use App\Enums\WarningLevel;
use App\Enums\WarningStatus;
use Database\Factories\WarningLetterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property WarningLevel $level
 * @property WarningStatus $status
 * @property int $points_snapshot
 * @property int $threshold
 * @property string|null $letter_number
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon $created_at
 */
class WarningLetter extends Model
{
    /** @use HasFactory<WarningLetterFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'level',
        'status',
        'points_snapshot',
        'threshold',
        'letter_number',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    /**
     * Whether the recommendation is still awaiting Guru BK's decision.
     */
    public function isPending(): bool
    {
        return $this->status === WarningStatus::Pending;
    }

    public function isIssued(): bool
    {
        return $this->status === WarningStatus::Approved;
    }

    /**
     * Approve the recommendation: the letter is issued and numbered (F-22).
     * Idempotent: a decided letter is never re-decided.
     */
    public function approve(User $decider, ?string $note = null): void
    {
        if (! $this->isPending()) {
            return;
        }

        $this->update([
            'status' => WarningStatus::Approved,
            'letter_number' => $this->generateLetterNumber(),
            'decided_by' => $decider->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    /**
     * Reject the recommendation with a mandatory reason (audit trail).
     */
    public function reject(User $decider, string $note): void
    {
        if (! $this->isPending()) {
            return;
        }

        $this->update([
            'status' => WarningStatus::Rejected,
            'decided_by' => $decider->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    /**
     * Sequential letter number, e.g. "003/SP2/VII/2026" — the sequence counts
     * every letter issued in the current calendar year.
     */
    private function generateLetterNumber(): string
    {
        $romanMonths = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        $sequence = static::query()
            ->where('status', WarningStatus::Approved)
            ->whereYear('decided_at', now()->year)
            ->count() + 1;

        return sprintf('%03d/SP%d/%s/%d', $sequence, $this->level->value, $romanMonths[now()->month], now()->year);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The Guru BK who approved/rejected the recommendation.
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
            'level' => WarningLevel::class,
            'status' => WarningStatus::class,
            'points_snapshot' => 'integer',
            'threshold' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}

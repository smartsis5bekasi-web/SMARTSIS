<?php

namespace App\Models;

use App\Actions\Point\ApplyPointAdjustment;
use App\Enums\PointApprovalStatus;
use App\Events\AchievementVerified;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $point_rule_id
 * @property string $title
 * @property int|null $input_by
 * @property int|null $verified_by
 * @property PointApprovalStatus $status
 * @property string|null $level
 * @property string|null $description
 * @property string|null $note
 * @property string|null $evidence_path
 * @property Carbon|null $achieved_on
 */
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'point_rule_id',
        'title',
        'input_by',
        'verified_by',
        'status',
        'level',
        'description',
        'note',
        'evidence_path',
        'achieved_on',
    ];

    /**
     * Whether the achievement is still awaiting verification.
     */
    public function isPending(): bool
    {
        return $this->status === PointApprovalStatus::Pending;
    }

    /**
     * Approve the achievement and let the point engine apply its addition.
     * Idempotent: re-approving an already-approved record is a no-op.
     */
    public function approve(User $verifier, ?string $note = null): void
    {
        if ($this->status === PointApprovalStatus::Approved) {
            return;
        }

        $this->update([
            'status' => PointApprovalStatus::Approved,
            'verified_by' => $verifier->id,
            'note' => $note,
        ]);

        AchievementVerified::dispatch($this, $verifier);
    }

    /**
     * Reject the achievement without affecting points.
     */
    public function reject(User $verifier, ?string $note = null): void
    {
        $this->update([
            'status' => PointApprovalStatus::Rejected,
            'verified_by' => $verifier->id,
            'note' => $note,
        ]);
    }

    /**
     * Reverse any points granted by this achievement (used before deleting an
     * approved record) so the student balance and audit trail stay consistent.
     */
    public function reversePoints(?User $by = null): void
    {
        $engine = app(ApplyPointAdjustment::class);

        $this->pointLogs()
            ->where('delta', '>', 0)
            ->get()
            ->each(fn (PointLog $log) => $engine->reverse($log, $by));
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<PointRule, $this>
     */
    public function pointRule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The point log entries produced by approving this achievement.
     *
     * @return MorphMany<PointLog, $this>
     */
    public function pointLogs(): MorphMany
    {
        return $this->morphMany(PointLog::class, 'source');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PointApprovalStatus::class,
            'achieved_on' => 'date',
        ];
    }
}

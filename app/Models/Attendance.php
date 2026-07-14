<?php

namespace App\Models;

use App\Actions\Point\ApplyPointAdjustment;
use App\Enums\AttendanceStatus;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property Carbon $date
 * @property AttendanceStatus $status
 * @property int|null $point_rule_id
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property string $method
 * @property int|null $recorded_by
 * @property string|null $note
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'date',
        'status',
        'point_rule_id',
        'checked_in_at',
        'checked_out_at',
        'method',
        'recorded_by',
        'note',
    ];

    /**
     * Whether the student has completed the end-of-day check-out (F-10).
     */
    public function isCheckedOut(): bool
    {
        return $this->checked_out_at !== null;
    }

    /**
     * Reverse the net point effect of this record (used before a manual
     * status correction) so the balance and audit trail stay consistent.
     *
     * Each attendance carries at most one active penalty at a time, so the
     * net of its logs is either zero (nothing to reverse) or the active
     * penalty entry.
     */
    public function reversePoints(?User $by = null, ?string $note = null): void
    {
        $net = (int) $this->pointLogs()->sum('delta');

        if ($net === 0) {
            return;
        }

        $log = $this->pointLogs()->where('delta', $net)->latest('id')->first();

        if ($log !== null) {
            app(ApplyPointAdjustment::class)->reverse($log, $by, $note);
        }
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    public function scopeOnDate(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('date', $date->toDateString());
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
     * The user who recorded the entry (kiosk operator or manual marker).
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The point log entries produced by this attendance record.
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
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }
}

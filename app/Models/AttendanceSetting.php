<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $late_after
 * @property string $check_out_after
 * @property int|null $late_rule_id
 * @property int|null $alpha_rule_id
 */
class AttendanceSetting extends Model
{
    /** @use HasFactory<AttendanceSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'late_after',
        'check_out_after',
        'late_rule_id',
        'alpha_rule_id',
    ];

    /**
     * The singleton settings row, created with defaults on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'late_after' => '07:00:00',
            'check_out_after' => '15:00:00',
        ]);
    }

    /**
     * The check-in status for the given moment: Hadir up to and including
     * the late threshold, Terlambat afterwards (PRD 7.3).
     */
    public function checkInStatus(CarbonInterface $at): AttendanceStatus
    {
        return $at->format('H:i:s') > $this->late_after
            ? AttendanceStatus::Terlambat
            : AttendanceStatus::Hadir;
    }

    /**
     * Whether check-out is already open at the given moment.
     */
    public function isCheckOutOpen(CarbonInterface $at): bool
    {
        return $at->format('H:i:s') >= $this->check_out_after;
    }

    /**
     * The active point rule to apply for the given status, if any.
     */
    public function ruleFor(AttendanceStatus $status): ?PointRule
    {
        $rule = match ($status) {
            AttendanceStatus::Terlambat => $this->lateRule,
            AttendanceStatus::Alpha => $this->alphaRule,
            default => null,
        };

        return $rule?->is_active ? $rule : null;
    }

    /**
     * @return BelongsTo<PointRule, $this>
     */
    public function lateRule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class, 'late_rule_id');
    }

    /**
     * @return BelongsTo<PointRule, $this>
     */
    public function alphaRule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class, 'alpha_rule_id');
    }
}

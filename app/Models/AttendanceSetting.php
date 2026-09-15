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
 * @property string $check_in_start
 * @property string $late_after
 * @property string $check_out_after
 * @property bool $ignore_schedule
 * @property int|null $late_rule_id
 * @property int|null $alpha_rule_id
 */
class AttendanceSetting extends Model
{
    /** @use HasFactory<AttendanceSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'check_in_start',
        'late_after',
        'check_out_after',
        'ignore_schedule',
        'late_rule_id',
        'alpha_rule_id',
    ];

    /**
     * The singleton settings row, created on first access with the defaults
     * for a 07:00–15:00 school day and a 30 minute late grace period.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'check_in_start' => '07:00:00',
            'late_after' => '07:30:00',
            'check_out_after' => '15:00:00',
            'ignore_schedule' => false,
        ]);
    }

    /**
     * Whether the check-in window has opened at the given moment. Attendance
     * recorded before it is rejected, so nobody can check in the night before
     * — unless an admin has opened absensi for testing.
     */
    public function isCheckInOpen(CarbonInterface $at): bool
    {
        return $this->ignore_schedule || $this->isCheckInTimeReached($at);
    }

    /**
     * The clock alone, ignoring the "buka kapan saja" override.
     */
    public function isCheckInTimeReached(CarbonInterface $at): bool
    {
        return $at->format('H:i:s') >= $this->check_in_start;
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
     * Whether check-out is already open at the given moment, or an admin has
     * opened absensi for testing.
     */
    public function isCheckOutOpen(CarbonInterface $at): bool
    {
        return $this->ignore_schedule || $this->isCheckOutTimeReached($at);
    }

    /**
     * The clock alone, ignoring the "buka kapan saja" override. The kiosk
     * picks its starting mode from this so that opening the windows for a
     * test does not land the operator on "pulang" first thing in the morning.
     */
    public function isCheckOutTimeReached(CarbonInterface $at): bool
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ignore_schedule' => 'boolean',
        ];
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

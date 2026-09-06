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
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $student_id
 * @property Carbon $date
 * @property AttendanceStatus $status
 * @property int|null $point_rule_id
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property string|null $check_in_photo_path
 * @property float|null $check_in_latitude
 * @property float|null $check_in_longitude
 * @property int|null $check_in_accuracy
 * @property string|null $check_out_photo_path
 * @property float|null $check_out_latitude
 * @property float|null $check_out_longitude
 * @property int|null $check_out_accuracy
 * @property string $method
 * @property int|null $recorded_by
 * @property string|null $note
 * @property string|null $attachment_path
 * @property string|null $reason
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
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
        'check_in_photo_path',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_accuracy',
        'check_out_photo_path',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_accuracy',
        'method',
        'recorded_by',
        'note',
        'attachment_path',
        'reason',
        'verified_at',
        'verified_by',
    ];

    /**
     * Whether the student has completed the end-of-day check-out (F-10).
     */
    public function isCheckedOut(): bool
    {
        return $this->checked_out_at !== null;
    }

    /**
     * Whether the record still needs an absensi pulang. Izin/sakit days end
     * the moment they are recorded — only a physical presence checks out.
     */
    public function needsCheckOut(): bool
    {
        return $this->status->isPresent() && ! $this->isCheckedOut();
    }

    /**
     * Whether a human (or the camera itself) has confirmed this record.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * The GPS fix recorded for the given moment, or null when the browser
     * never handed one over.
     *
     * @param  'in'|'out'  $moment
     * @return array{latitude: float, longitude: float, accuracy: int|null}|null
     */
    public function location(string $moment): ?array
    {
        $latitude = $moment === 'out' ? $this->check_out_latitude : $this->check_in_latitude;
        $longitude = $moment === 'out' ? $this->check_out_longitude : $this->check_in_longitude;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $moment === 'out' ? $this->check_out_accuracy : $this->check_in_accuracy,
        ];
    }

    /**
     * Whether any GPS fix at all was captured for this record.
     */
    public function hasLocation(): bool
    {
        return $this->location('in') !== null || $this->location('out') !== null;
    }

    /**
     * The photo to represent this record in the riwayat table: the check-in
     * selfie, falling back to the check-out one, then the sakit/izin proof.
     */
    public function evidencePhotoUrl(): ?string
    {
        return $this->check_in_photo_path
            ?? $this->check_out_photo_path
            ?? $this->imageAttachmentUrl();
    }

    /**
     * The sakit/izin attachment, but only when it is an image (a PDF surat is
     * linked instead of previewed).
     */
    public function imageAttachmentUrl(): ?string
    {
        if ($this->attachment_path === null) {
            return null;
        }

        return Str::endsWith(Str::lower($this->attachment_path), '.pdf') ? null : $this->attachment_path;
    }

    /**
     * Mark the record as confirmed by a member of staff.
     */
    public function markVerified(?User $by = null): void
    {
        $this->forceFill([
            'verified_at' => now(),
            'verified_by' => $by?->id,
        ])->save();
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
     * The staff member who confirmed a self-declared sakit/izin.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
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
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_in_accuracy' => 'integer',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'check_out_accuracy' => 'integer',
            'verified_at' => 'datetime',
        ];
    }
}

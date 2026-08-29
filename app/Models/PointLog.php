<?php

namespace App\Models;

use App\Enums\PointType;
use Carbon\CarbonInterface;
use Database\Factories\PointLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int|null $point_rule_id
 * @property PointType $type
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $delta
 * @property int $balance_after
 * @property string|null $note
 * @property int|null $created_by
 */
class PointLog extends Model
{
    /** @use HasFactory<PointLogFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'point_rule_id',
        'type',
        'source_type',
        'source_id',
        'delta',
        'balance_after',
        'note',
        'created_by',
    ];

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
     * The record that triggered this point change (violation, achievement, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The day the thing behind this entry actually happened, which is not the
     * day the entry was written.
     *
     * A guru piket catching up on a week of absences marks Monday through
     * Thursday in one sitting on Thursday afternoon; every log row then carries
     * Thursday's `created_at`, and a student looking at their history sees four
     * identical "Alpha, Thursday" lines for four different days. So each source
     * reports its own date and `created_at` is only the fallback for manual
     * adjustments that have no source.
     */
    public function occurredAt(): ?CarbonInterface
    {
        $source = $this->source;

        $occurred = match (true) {
            $source instanceof Attendance => $source->date,
            $source instanceof Violation => $source->occurred_on,
            $source instanceof Achievement => $source->achieved_on,
            default => null,
        };

        return $occurred ?? $this->created_at;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PointType::class,
            'delta' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}

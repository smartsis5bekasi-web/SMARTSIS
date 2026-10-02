<?php

namespace App\Models;

use App\Enums\CounselingAction;
use Database\Factories\CounselingRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pemanggilan by Guru BK: the student was called in for one of the
 * recommended follow-ups, with BK's own notes on what was discussed.
 *
 * @property int $id
 * @property int $student_id
 * @property CounselingAction $action
 * @property Carbon $called_on
 * @property string $note
 * @property int|null $recorded_by
 * @property Carbon $created_at
 */
class CounselingRecord extends Model
{
    /** @use HasFactory<CounselingRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'action',
        'called_on',
        'note',
        'recorded_by',
    ];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The Guru BK who recorded the pemanggilan.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => CounselingAction::class,
            'called_on' => 'date',
        ];
    }
}

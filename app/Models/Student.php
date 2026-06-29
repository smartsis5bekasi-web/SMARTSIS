<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $nis
 * @property string|null $nisn
 * @property string $name
 * @property string|null $avatar_url
 * @property string|null $gender
 * @property Carbon|null $birth_date
 * @property string|null $address
 * @property int|null $classroom_id
 * @property int|null $teacher_id
 * @property int|null $major_id
 * @property int|null $year_in
 * @property int $current_point
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nis',
        'nisn',
        'name',
        'avatar_url',
        'gender',
        'birth_date',
        'address',
        'classroom_id',
        'teacher_id',
        'major_id',
        'year_in',
        'current_point',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return BelongsTo<Major, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    /**
     * The parents/guardians linked to this student.
     *
     * @return BelongsToMany<ParentGuardian, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentGuardian::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    /**
     * The student's full point-change history (audit trail).
     *
     * @return HasMany<PointLog, $this>
     */
    public function pointLogs(): HasMany
    {
        return $this->hasMany(PointLog::class);
    }

    /**
     * @return HasMany<Violation, $this>
     */
    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    /**
     * @return HasMany<Achievement, $this>
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'current_point' => 'integer',
            'year_in' => 'integer',
        ];
    }
}

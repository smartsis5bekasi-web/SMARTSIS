<?php

namespace App\Models;

use App\Support\SchoolCalendar;
use Carbon\CarbonInterface;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|null $nisn_verified_at
 * @property array<int, array<int, float>>|null $face_descriptors
 * @property Carbon|null $face_registered_at
 * @property Carbon|null $onboarded_at
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // New students start with the admin-configured initial point (F-12)
        // unless a balance is provided explicitly (e.g. imports, factories).
        static::creating(function (Student $student): void {
            if ($student->getAttribute('current_point') === null) {
                $student->current_point = PointSetting::current()->initial_point;
            }
        });
    }

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
        'nisn_verified_at',
        'face_descriptors',
        'face_registered_at',
        'onboarded_at',
    ];

    /**
     * Whether the student has finished the first-login onboarding
     * (NISN verified, face registered, and confirmed).
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarded_at !== null;
    }

    /**
     * Whether the student has confirmed their NISN during onboarding.
     */
    public function hasVerifiedNisn(): bool
    {
        return $this->nisn_verified_at !== null;
    }

    /**
     * Whether a face template is stored for Smart Attendance matching.
     */
    public function hasRegisteredFace(): bool
    {
        return filled($this->face_descriptors);
    }

    /**
     * Whether the student still has to go through onboarding before using the
     * app. The face template is mandatory because the classroom kiosk
     * identifies students by face, so a student who finished onboarding back
     * when the face step could be skipped is sent back to register one.
     */
    public function needsOnboarding(): bool
    {
        return ! $this->hasCompletedOnboarding() || ! $this->hasRegisteredFace();
    }

    /**
     * Narrow to the students who are expected at school on the date: nobody
     * on a school-wide holiday, and nobody in a grade that has the day off.
     * A student whose classroom has no grade only ever has school-wide
     * holidays off. Weekends are the caller's concern.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeExpectedOn(Builder $query, CarbonInterface $date, ?SchoolCalendar $calendar = null): void
    {
        $calendar ??= SchoolCalendar::on($date);

        if ($calendar->schoolWideHolidayOn($date) !== null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $gradesOff = $calendar->gradesOffOn($date);

        if ($gradesOff !== []) {
            $query->whereDoesntHave('classroom', fn (Builder $classroom) => $classroom->whereIn('grade', $gradesOff));
        }
    }

    /**
     * The opposite of {@see scopeExpectedOn()}: the students who have the
     * date off.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeOnHoliday(Builder $query, CarbonInterface $date, ?SchoolCalendar $calendar = null): void
    {
        $calendar ??= SchoolCalendar::on($date);

        if ($calendar->schoolWideHolidayOn($date) !== null) {
            return;
        }

        $gradesOff = $calendar->gradesOffOn($date);

        if ($gradesOff === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('classroom', fn (Builder $classroom) => $classroom->whereIn('grade', $gradesOff));
    }

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
     * @return BelongsToMany<ParentGuardian, $this, ParentStudent>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentGuardian::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot('relationship')
            ->using(ParentStudent::class)
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
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<Permit, $this>
     */
    public function permits(): HasMany
    {
        return $this->hasMany(Permit::class);
    }

    /**
     * @return HasMany<WarningLetter, $this>
     */
    public function warningLetters(): HasMany
    {
        return $this->hasMany(WarningLetter::class);
    }

    /**
     * Pemanggilan BK recorded for the student.
     *
     * @return HasMany<CounselingRecord, $this>
     */
    public function counselingRecords(): HasMany
    {
        return $this->hasMany(CounselingRecord::class);
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
            'nisn_verified_at' => 'datetime',
            'face_descriptors' => 'array',
            'face_registered_at' => 'datetime',
            'onboarded_at' => 'datetime',
        ];
    }
}

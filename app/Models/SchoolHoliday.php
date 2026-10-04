<?php

namespace App\Models;

use App\Enums\GradeLevel;
use App\Support\SchoolCalendar;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Database\Factories\SchoolHolidayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A day or range of days on which (some) students are not expected at school:
 * tanggal merah, cuti bersama (SKB 3 Menteri), or a closure the school calls
 * itself — a bencana, or a kegiatan for which only some grades come in.
 *
 * Nobody is flagged "Menunggu Konfirmasi" for a day they had off, absensi is
 * closed to them, and a school-wide holiday does not count as a school day
 * towards the alpha-otomatis deadline. See {@see SchoolCalendar}.
 *
 * @property int $id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property array<int, int>|null $grades
 * @property int|null $created_by
 */
class SchoolHoliday extends Model
{
    /** @use HasFactory<SchoolHolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'grades',
        'created_by',
    ];

    /**
     * Whether every student is off, rather than only some grades.
     */
    public function isSchoolWide(): bool
    {
        return $this->grades === null || $this->grades === [];
    }

    /**
     * Whether the holiday falls on the given date.
     */
    public function covers(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return $this->start_date->toDateString() <= $day && $day <= $this->end_date->toDateString();
    }

    /**
     * Whether a student of the given grade is off. A student whose classroom
     * has no grade recorded is only covered by a school-wide holiday.
     */
    public function appliesToGrade(?GradeLevel $grade): bool
    {
        if ($this->isSchoolWide()) {
            return true;
        }

        return $grade !== null && in_array($grade->value, $this->grades, true);
    }

    /**
     * @return array<int, GradeLevel>
     */
    public function gradeLevels(): array
    {
        if ($this->isSchoolWide()) {
            return GradeLevel::cases();
        }

        return array_values(array_filter(array_map(
            fn (int $grade): ?GradeLevel => GradeLevel::tryFrom($grade),
            $this->grades,
        )));
    }

    /**
     * Who is off, as shown to users: "Seluruh sekolah" or "Kelas X, Kelas XI".
     */
    public function scopeLabel(): string
    {
        if ($this->isSchoolWide()) {
            return __('Seluruh sekolah');
        }

        return implode(', ', array_map(fn (GradeLevel $grade): string => $grade->label(), $this->gradeLevels()));
    }

    /**
     * The dates as shown to users: "5 Okt 2026" or "5 – 9 Okt 2026".
     */
    public function periodLabel(): string
    {
        if ($this->start_date->isSameDay($this->end_date)) {
            return $this->start_date->translatedFormat('j M Y');
        }

        $format = $this->start_date->isSameYear($this->end_date) ? 'j M' : 'j M Y';

        return $this->start_date->translatedFormat($format).' – '.$this->end_date->translatedFormat('j M Y');
    }

    /**
     * Every calendar day of the holiday.
     *
     * @return CarbonPeriod<Carbon>
     */
    public function days(): CarbonPeriod
    {
        return CarbonPeriod::create($this->start_date->copy()->startOfDay(), $this->end_date->copy()->startOfDay());
    }

    /**
     * The number of calendar days the holiday spans.
     */
    public function dayCount(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * @param  Builder<SchoolHoliday>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, ?CarbonInterface $to = null): void
    {
        $query->whereDate('end_date', '>=', $from->toDateString())
            ->when($to !== null, fn (Builder $inner) => $inner->whereDate('start_date', '<=', $to->toDateString()));
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
            'start_date' => 'date',
            'end_date' => 'date',
            'grades' => 'array',
        ];
    }
}

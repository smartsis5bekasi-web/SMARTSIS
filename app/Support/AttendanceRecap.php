<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The filters behind "Rekap Absensi": per-student totals for every status in
 * a date range, optionally narrowed to students who reached a minimum count
 * of one status (e.g. "Terlambat minimal 3 kali").
 *
 * The page and its Excel export both apply the same instance, so the export
 * always contains exactly what the filtered table shows.
 */
final readonly class AttendanceRecap
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public ?int $classroomId = null,
        public string $search = '',
        public ?AttendanceStatus $thresholdStatus = null,
        public int $minCount = 1,
    ) {}

    /**
     * Add the per-status counts and every active filter to a (role-scoped)
     * student query.
     *
     * @param  Builder<Student>  $students
     * @return Builder<Student>
     */
    public function apply(Builder $students): Builder
    {
        $counts = [];

        foreach (AttendanceStatus::cases() as $case) {
            $counts['attendances as '.$case->value.'_count'] = fn (Builder $query) => $this->inRange($query)
                ->where('status', $case->value);
        }

        return $students
            ->withCount($counts)
            ->when($this->classroomId !== null, fn (Builder $query) => $query->where('classroom_id', $this->classroomId))
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('nis', 'like', '%'.$this->search.'%'),
            ))
            ->when($this->thresholdStatus !== null, fn (Builder $query) => $query
                ->whereHas(
                    'attendances',
                    fn (Builder $inner) => $this->inRange($inner)->where('status', $this->thresholdStatus->value),
                    '>=',
                    max(1, $this->minCount),
                )
                ->orderByDesc($this->thresholdStatus->value.'_count'))
            ->orderBy('name');
    }

    /**
     * Whether the "minimal N kali" filter is narrowing the rows.
     */
    public function isThresholdActive(): bool
    {
        return $this->thresholdStatus !== null;
    }

    /**
     * A one-line description of the active filters, for the export notes.
     */
    public function describe(): string
    {
        $parts = [
            'Periode '.$this->from->translatedFormat('d M Y').' – '.$this->to->translatedFormat('d M Y'),
        ];

        if ($this->thresholdStatus !== null) {
            $parts[] = $this->thresholdStatus->label().' minimal '.max(1, $this->minCount).' kali';
        }

        if ($this->search !== '') {
            $parts[] = 'pencarian "'.$this->search.'"';
        }

        return implode(', ', $parts);
    }

    /**
     * The student's total for one status, as loaded by {@see self::apply()}.
     */
    public static function count(Student $student, AttendanceStatus $status): int
    {
        return (int) $student->getAttribute($status->value.'_count');
    }

    /**
     * Attendance rate: days present (hadir + terlambat) out of all recorded
     * days in the range — a day still waiting for confirmation counts as a
     * day missed. Null when the student has no record yet.
     */
    public static function presenceRate(Student $student): ?int
    {
        $present = self::count($student, AttendanceStatus::Hadir) + self::count($student, AttendanceStatus::Terlambat);
        $total = array_sum(array_map(fn (AttendanceStatus $status): int => self::count($student, $status), AttendanceStatus::cases()));

        return $total > 0 ? (int) round($present / $total * 100) : null;
    }

    /**
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function inRange(Builder $query): Builder
    {
        return $query->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()]);
    }
}

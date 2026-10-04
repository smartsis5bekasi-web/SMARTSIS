<?php

namespace App\Support;

use App\Enums\GradeLevel;
use App\Models\SchoolHoliday;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Answers "is this a school day, and for whom?" from the declared
 * {@see SchoolHoliday} rows, on top of the fixed Monday–Friday week.
 *
 * The holidays are loaded once for the window the caller asks about and then
 * answered in memory, so a sweep over thousands of records does not query per
 * record. A window left open at the end ({@see self::from()}) covers every
 * holiday still to come, which a deadline that has to skip holidays needs.
 */
final class SchoolCalendar
{
    /**
     * @param  Collection<int, SchoolHoliday>  $holidays
     */
    private function __construct(private readonly Collection $holidays) {}

    /**
     * The calendar for a single day.
     */
    public static function on(CarbonInterface $date): self
    {
        return self::between($date, $date);
    }

    /**
     * The calendar for the given date onwards, with no end.
     */
    public static function from(CarbonInterface $date): self
    {
        return self::between($date, null);
    }

    public static function between(CarbonInterface $from, ?CarbonInterface $to): self
    {
        return new self(
            SchoolHoliday::query()->overlapping($from, $to)->orderBy('start_date')->orderBy('id')->get(),
        );
    }

    /**
     * Every loaded holiday falling on the date.
     *
     * @return Collection<int, SchoolHoliday>
     */
    public function holidaysOn(CarbonInterface $date): Collection
    {
        return $this->holidays->filter(fn (SchoolHoliday $holiday): bool => $holiday->covers($date))->values();
    }

    /**
     * The holiday that closes the whole school on the date, if any.
     */
    public function schoolWideHolidayOn(CarbonInterface $date): ?SchoolHoliday
    {
        return $this->holidaysOn($date)->first(fn (SchoolHoliday $holiday): bool => $holiday->isSchoolWide());
    }

    /**
     * The holiday a student of the given grade has on the date, if any. A
     * school-wide holiday wins over a grade one, so the message names it.
     */
    public function holidayForGrade(?GradeLevel $grade, CarbonInterface $date): ?SchoolHoliday
    {
        return $this->schoolWideHolidayOn($date)
            ?? $this->holidaysOn($date)->first(fn (SchoolHoliday $holiday): bool => $holiday->appliesToGrade($grade));
    }

    /**
     * The holiday the student has on the date, if any, going by the grade of
     * their classroom.
     */
    public function holidayFor(Student $student, CarbonInterface $date): ?SchoolHoliday
    {
        return $this->holidayForGrade($student->classroom?->grade, $date);
    }

    /**
     * The grades that are off on the date. Every grade on a school-wide
     * holiday.
     *
     * @return array<int, int>
     */
    public function gradesOffOn(CarbonInterface $date): array
    {
        if ($this->schoolWideHolidayOn($date) !== null) {
            return GradeLevel::values();
        }

        return $this->holidaysOn($date)
            ->flatMap(fn (SchoolHoliday $holiday): array => $holiday->grades ?? [])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Whether the school is open on the date: a weekday with no school-wide
     * holiday. A day only some grades have off is still a school day — the
     * teachers who confirm attendance are in.
     */
    public function isSchoolDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && $this->schoolWideHolidayOn($date) === null;
    }

    /**
     * The date the given number of school days after the date, skipping
     * weekends and school-wide holidays. Needs a calendar loaded from the
     * date onwards ({@see self::from()}).
     */
    public function addSchoolDays(CarbonInterface $date, int $days): Carbon
    {
        $cursor = Carbon::parse($date)->startOfDay();

        // A holiday is a finite range, so this always ends; the cap only
        // guards against a corrupt row (an end year typed as 9999).
        for ($counted = 0, $guard = 0; $counted < $days && $guard < 3660; $guard++) {
            $cursor->addDay();

            if ($this->isSchoolDay($cursor)) {
                $counted++;
            }
        }

        return $cursor;
    }
}

<?php

namespace App\Livewire\Concerns;

use App\Actions\Attendance\RecordAttendance;
use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\SchoolHoliday;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The Hari Libur form shared by the create and edit pages: the fields, their
 * rules, and saving — which also cleans up anything the system already put
 * on the books for the days the holiday covers.
 */
trait EditsSchoolHoliday
{
    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    /** "all" for the whole school, "grades" for the grades picked below. */
    public string $scope = 'all';

    /** @var array<int, int|string> */
    public array $grades = [];

    /**
     * The longest range one entry may span; anything longer is a typo (a
     * year off by one), not a holiday.
     */
    private const MAX_DAYS = 366;

    protected function fillHolidayForm(SchoolHoliday $holiday): void
    {
        $this->name = $holiday->name;
        $this->start_date = $holiday->start_date->toDateString();
        $this->end_date = $holiday->end_date->toDateString();
        $this->scope = $holiday->isSchoolWide() ? 'all' : 'grades';
        $this->grades = $holiday->isSchoolWide() ? [] : $holiday->grades;
    }

    /**
     * A single-day holiday is the common case, so the end follows the start
     * until someone sets it apart.
     */
    public function updatedStartDate(): void
    {
        if ($this->end_date === '' || $this->end_date < $this->start_date) {
            $this->end_date = $this->start_date;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => [
                'required', 'date_format:Y-m-d', 'after_or_equal:start_date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $start = rescue(fn () => Carbon::createFromFormat('Y-m-d', $this->start_date), null, false);
                    $end = rescue(fn () => Carbon::createFromFormat('Y-m-d', (string) $value), null, false);

                    if ($start !== null && $end !== null && $start->diffInDays($end) + 1 > self::MAX_DAYS) {
                        $fail(__('Rentang hari libur paling lama :days hari.', ['days' => self::MAX_DAYS]));
                    }
                },
            ],
            'scope' => ['required', Rule::in(['all', 'grades'])],
            'grades' => ['array', Rule::requiredIf($this->scope === 'grades')],
            'grades.*' => ['integer', Rule::enum(GradeLevel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('keterangan'),
            'start_date' => __('tanggal mulai'),
            'end_date' => __('tanggal selesai'),
            'grades' => __('tingkat'),
        ];
    }

    /**
     * @return array<int, GradeLevel>
     */
    public function gradeOptions(): array
    {
        return GradeLevel::cases();
    }

    /**
     * Classrooms with no tingkat yet: a grade holiday cannot reach them, so
     * the form warns before their students are swept as absent.
     */
    public function classroomsWithoutGrade(): int
    {
        return Classroom::query()->whereNull('grade')->count();
    }

    /**
     * Validate and store the holiday, then drop the pending and unreviewed
     * automatic Alpha records it makes moot.
     *
     * @return int the number of attendance records released
     */
    protected function saveHoliday(?SchoolHoliday $holiday = null): int
    {
        $data = $this->validate();

        $grades = $data['scope'] === 'grades'
            ? collect($data['grades'])->map(fn (mixed $grade): int => (int) $grade)->unique()->sort()->values()->all()
            : [];

        // Every grade picked is the whole school — store it as such, so it
        // also closes the school for the alpha-otomatis deadline.
        $attributes = [
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'grades' => $grades === [] || count($grades) === count(GradeLevel::cases()) ? null : $grades,
        ];

        if ($holiday === null) {
            $holiday = SchoolHoliday::create([...$attributes, 'created_by' => auth()->id()]);
        } else {
            $holiday->update($attributes);
        }

        return app(RecordAttendance::class)->releaseHoliday($holiday->fresh(), auth()->user());
    }

    /**
     * The toast after saving, mentioning any records that were released.
     */
    protected function savedMessage(string $message, int $released): string
    {
        if ($released === 0) {
            return $message;
        }

        return $message.' '.__(':count absensi tertunda / Alpha otomatis pada hari libur dibatalkan.', ['count' => $released]);
    }
}

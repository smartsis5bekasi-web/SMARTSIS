<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\Student;
use App\Support\SchoolCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Daily attendance monitor rows for a single date, scoped by the same
 * filters (classroom, status, search) as the Absensi index page.
 *
 * @implements WithMapping<Student>
 */
class AttendanceDailyExports extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    private ?SchoolCalendar $calendar = null;

    public function __construct(
        private readonly Carbon $date,
        private readonly ?int $classroomId = null,
        private readonly ?string $status = null,
        private readonly string $search = '',
    ) {}

    public function title(): string
    {
        return 'Absensi '.$this->date->format('d-m-Y');
    }

    /**
     * @return Collection<int, Student>
     */
    public function collection(): Collection
    {
        $calendar = $this->calendar();

        return Student::query()
            ->with(['classroom', 'attendances' => fn ($query) => $query->whereDate('date', $this->date->toDateString())])
            ->when($this->classroomId !== null, fn ($query) => $query->where('classroom_id', $this->classroomId))
            ->when($this->search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('nis', 'like', '%'.$this->search.'%'),
            ))
            ->when($this->status === 'none', fn ($query) => $query->expectedOn($this->date, $calendar)->whereDoesntHave(
                'attendances',
                fn ($inner) => $inner->whereDate('date', $this->date->toDateString()),
            ))
            ->when($this->status === 'holiday', fn ($query) => $query->onHoliday($this->date, $calendar)->whereDoesntHave(
                'attendances',
                fn ($inner) => $inner->whereDate('date', $this->date->toDateString()),
            ))
            ->when($this->status !== null && ! in_array($this->status, ['', 'none', 'holiday'], true), fn ($query) => $query->whereHas(
                'attendances',
                fn ($inner) => $inner->whereDate('date', $this->date->toDateString())->where('status', $this->status),
            ))
            ->orderBy('name')
            ->get();
    }

    private function calendar(): SchoolCalendar
    {
        return $this->calendar ??= SchoolCalendar::on($this->date);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama', 'nis', 'kelas', 'jam_masuk', 'jam_pulang', 'status', 'keterangan'];
    }

    /**
     * @param  Student  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        $attendance = $row->attendances->first();
        $holiday = $attendance === null ? $this->calendar()->holidayFor($row, $this->date) : null;

        return [
            $row->name,
            $row->nis,
            $row->classroom?->name,
            $attendance?->checked_in_at?->format('H:i'),
            $attendance?->checked_out_at?->format('H:i'),
            $attendance?->status?->label() ?? ($holiday !== null ? 'Libur' : 'Belum Absen'),
            $attendance->note ?? $holiday?->name,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['B'], max($sheet->getHighestRow(), 2));

        return [];
    }
}

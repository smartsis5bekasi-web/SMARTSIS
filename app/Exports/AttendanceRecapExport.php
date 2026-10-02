<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
use App\Exports\Concerns\StylesSheet;
use App\Models\Student;
use App\Support\AttendanceRecap;
use Illuminate\Database\Eloquent\Builder;
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
 * Per-student attendance totals for the "Rekap Absensi" page, with exactly
 * the filters (period, class, search, minimal-count) the table is showing.
 *
 * @implements WithMapping<Student>
 */
class AttendanceRecapExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    /**
     * @param  Builder<Student>  $students  The role-scoped student query.
     */
    public function __construct(
        private readonly Builder $students,
        private readonly AttendanceRecap $recap,
    ) {}

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    /**
     * @return Collection<int, Student>
     */
    public function collection(): Collection
    {
        return $this->recap->apply(clone $this->students)
            ->with('classroom')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'nama',
            'nis',
            'kelas',
            ...array_map(fn (AttendanceStatus $status): string => str($status->label())->snake()->value(), AttendanceStatus::cases()),
            'kehadiran_persen',
        ];
    }

    /**
     * @param  Student  $row
     * @return array<int, string|int|null>
     */
    public function map($row): array
    {
        $rate = AttendanceRecap::presenceRate($row);

        return [
            $row->name,
            $row->nis,
            $row->classroom?->name,
            ...array_map(fn (AttendanceStatus $status): int => AttendanceRecap::count($row, $status), AttendanceStatus::cases()),
            $rate === null ? null : $rate.'%',
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['B'], max($sheet->getHighestRow(), 2));
        $this->writeNotes($sheet, [$this->recap->describe()]);

        return [];
    }
}

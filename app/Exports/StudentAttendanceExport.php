<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\Attendance;
use App\Models\Student;
use Carbon\CarbonInterface;
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
 * Every attendance record of one student (the "Detail Absensi Siswa" page),
 * with the same period and status filters the page is showing.
 *
 * @implements WithMapping<Attendance>
 */
class StudentAttendanceExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    public function __construct(
        private readonly Student $student,
        private readonly ?CarbonInterface $from = null,
        private readonly ?CarbonInterface $to = null,
        private readonly string $status = '',
    ) {}

    public function title(): string
    {
        return str('Absensi '.$this->student->name)->limit(31, '')->value();
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function collection(): Collection
    {
        return $this->student->attendances()
            ->when($this->from !== null, fn ($query) => $query->whereDate('date', '>=', $this->from->toDateString()))
            ->when($this->to !== null, fn ($query) => $query->whereDate('date', '<=', $this->to->toDateString()))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('date')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['tanggal', 'hari', 'status', 'jam_masuk', 'jam_pulang', 'keterangan'];
    }

    /**
     * @param  Attendance  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        return [
            $row->date->format('d-m-Y'),
            $row->date->translatedFormat('l'),
            $row->status->label(),
            $row->checked_in_at?->format('H:i:s'),
            $row->checked_out_at?->format('H:i:s'),
            $row->note ?? $row->reason,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->writeNotes($sheet, [$this->student->name.' (NIS '.$this->student->nis.') — '.($this->student->classroom->name ?? 'tanpa kelas')]);

        return [];
    }
}

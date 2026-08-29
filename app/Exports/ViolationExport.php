<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\Violation;
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
 * @implements WithMapping<Violation>
 */
class ViolationExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    /**
     * @param  Builder<Violation>  $baseQuery
     */
    public function __construct(
        private readonly Builder $baseQuery,
        private readonly string $status = '',
    ) {}

    public function title(): string
    {
        return 'Data Pelanggaran Siswa';
    }

    /**
     * @return Collection<int, Violation>
     */
    public function collection(): Collection
    {
        return (clone $this->baseQuery)
            ->with(['student.classroom', 'pointRule'])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->latest()
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'nama_siswa',
            'nis',
            'kelas',
            'pelanggaran',
            'poin_pengurangan',
            'status_persetujuan',
            'tanggal_kejadian',
            'catatan',
        ];
    }

    /**
     * @param  Violation  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        return [
            $row->student?->name,
            $row->student?->nis,
            $row->student?->classroom?->name,
            $row->pointRule?->name,
            '-'.($row->pointRule->point ?? 0),
            $row->status->label(),
            $row->occurred_on?->format('d-m-Y'),
            $row->note,
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

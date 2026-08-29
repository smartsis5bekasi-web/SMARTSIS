<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\Permit;
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
 * @implements WithMapping<Permit>
 */
class PermitExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    /**
     * @param  Builder<Permit>  $baseQuery
     */
    public function __construct(
        private readonly Builder $baseQuery,
        private readonly string $status = '',
        private readonly string $type = '',
    ) {}

    public function title(): string
    {
        return 'Data Perizinan';
    }

    /**
     * @return Collection<int, Permit>
     */
    public function collection(): Collection
    {
        return (clone $this->baseQuery)
            ->with(['student.classroom', 'decider'])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
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
            'jenis_izin',
            'tanggal_izin',
            'alasan',
            'status',
            'diputuskan_oleh',
            'tgl_diputuskan',
            'catatan_keputusan',
        ];
    }

    /**
     * @param  Permit  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        return [
            $row->student?->name,
            $row->student?->nis,
            $row->student?->classroom?->name,
            $row->type->label(),
            $row->date->format('d-m-Y'),
            $row->reason,
            $row->status->label(),
            $row->decider?->name,
            $row->decided_at?->format('d-m-Y H:i'),
            $row->decision_note,
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

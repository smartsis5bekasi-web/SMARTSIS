<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\ParentGuardian;
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
 * @implements WithMapping<ParentGuardian>
 */
class ParentsExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Data Orang Tua';
    }

    /**
     * @return Collection<int, ParentGuardian>
     */
    public function collection(): Collection
    {
        return ParentGuardian::query()->with('user')->orderBy('name')->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama', 'email', 'telepon', 'status_akun'];
    }

    /**
     * @param  ParentGuardian  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        return [
            $row->name,
            $row->user?->email,
            $row->phone,
            $row->user?->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['C'], max($sheet->getHighestRow(), 2));

        return [];
    }
}

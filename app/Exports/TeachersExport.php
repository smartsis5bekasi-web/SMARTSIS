<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\Teacher;
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
 * All guru rows using the same columns as the import template, so an export
 * can be edited and re-imported.
 *
 * @implements WithMapping<Teacher>
 */
class TeachersExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Data Guru';
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function collection(): Collection
    {
        return Teacher::query()->with('user.roles')->orderBy('name')->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama', 'nip', 'email', 'telepon', 'peran'];
    }

    /**
     * @param  Teacher  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        return [
            $row->name,
            $row->nip,
            $row->user?->email,
            $row->phone,
            $row->user?->primaryRole()?->value,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['B', 'D'], max($sheet->getHighestRow(), 2));

        return [];
    }
}

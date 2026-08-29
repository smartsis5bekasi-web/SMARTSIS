<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\StylesSheet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The fill-in sheet of the orang tua import template: header plus sample rows.
 * Also the single source of the sample shown in the import modal.
 */
class ParentTemplateSheet extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Data Orang Tua';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama', 'email', 'telepon', 'password'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['Siti Aminah', 'siti.aminah@email.com', '081234567890', 'rahasia123'],
            ['Joko Widodo', 'joko.widodo@email.com', '081298765432', ''],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['C'], 300);

        return [];
    }
}

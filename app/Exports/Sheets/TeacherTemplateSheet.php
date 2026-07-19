<?php

namespace App\Exports\Sheets;

use App\Enums\UserRole;
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
 * The fill-in sheet of the guru import template: header plus sample rows.
 * Also the single source of the sample shown in the import modal.
 */
class TeacherTemplateSheet extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Data Guru';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama', 'nip', 'email', 'telepon', 'peran', 'password'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['Budi Santoso', '198001012005011001', 'budi.santoso@sekolah.sch.id', '081234567890', UserRole::GuruMapel->value, 'rahasia123'],
            ['Siti Aminah', '198203152006042002', 'siti.aminah@sekolah.sch.id', '081298765432', UserRole::WaliKelas->value, ''],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['B', 'D'], 300);

        return [];
    }
}

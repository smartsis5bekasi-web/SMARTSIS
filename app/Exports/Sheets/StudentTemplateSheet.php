<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\StylesSheet;
use App\Models\Classroom;
use App\Models\Major;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The fill-in sheet of the siswa import template: header plus sample rows
 * that reference real kelas/jurusan names so the sample is importable as-is.
 * Also the single source of the sample shown in the import modal.
 */
class StudentTemplateSheet extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Data Siswa';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama', 'nis', 'nisn','email', 'password', 'jenis_kelamin', 'tanggal_lahir', 'alamat', 'kelas', 'jurusan', 'orang_tua', 'hubungan', 'telepon_orang_tua'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $kelas = Classroom::query()->orderBy('name')->value('name') ?? 'X IPA 1';
        $jurusan = Major::query()->orderBy('name')->value('name') ?? 'IPA';

        return [
            ['Ahmad Fauzi', '2024001', '0051234567', 'siswa@smartis.com', 'password', 'L', '2008-03-15', 'Jl. Merdeka No. 1', $kelas, $jurusan, 'Budi Fauzi', 'Ayah', '081234567890'],
            ['Dewi Lestari', '2024002', '', 'siswa@smartis.com', 'password', 'P', '2008-07-20', '', $kelas, '', 'Sari Lestari', 'Ibu', '081298765432'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['B', 'C', 'E', 'K'], 500);

        return [];
    }
}

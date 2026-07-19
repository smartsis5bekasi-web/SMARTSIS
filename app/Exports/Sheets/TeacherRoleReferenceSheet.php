<?php

namespace App\Exports\Sheets;

use App\Enums\UserRole;
use App\Exports\Concerns\StylesSheet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reference sheet listing the valid values for the "peran" relation column
 * of the guru import template.
 */
class TeacherRoleReferenceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Referensi Peran';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['peran (isi kolom peran dengan nilai ini)', 'keterangan'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return array_map(
            fn (UserRole $role): array => [$role->value, $role->label()],
            UserRole::teacherRoles(),
        );
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->writeNotes($sheet, [
            'Kolom peran juga boleh diisi labelnya, misal "Guru Mata Pelajaran".',
            'Kolom nama, email, dan peran wajib diisi; email & NIP harus unik.',
            'Kolom password opsional — jika kosong, akun dibuat dengan password "password".',
        ]);

        return [];
    }
}

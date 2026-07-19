<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\Student;
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
 * All siswa rows using the same columns as the import template, so an
 * export can be edited and re-imported. Only the first orang tua/wali is
 * exported because the template holds one per row.
 */
class StudentsExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Data Siswa';
    }

    /**
     * @return Collection<int, Student>
     */
    public function collection(): Collection
    {
        return Student::query()->with(['classroom', 'major', 'parents'])->orderBy('name')->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama', 'nis', 'nisn', 'jenis_kelamin', 'tanggal_lahir', 'alamat', 'kelas', 'jurusan', 'orang_tua', 'hubungan', 'telepon_orang_tua'];
    }

    /**
     * @param  Student  $row
     * @return array<int, string|null>
     */
    public function map($row): array
    {
        $parent = $row->parents->first();

        return [
            $row->name,
            $row->nis,
            $row->nisn,
            $row->gender,
            $row->birth_date?->format('Y-m-d'),
            $row->address,
            $row->classroom?->name,
            $row->major?->name,
            $parent?->name,
            $parent?->pivot->relationship,
            $parent?->phone,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->formatColumnsAsText($sheet, ['B', 'C', 'E', 'K'], max($sheet->getHighestRow(), 2));

        return [];
    }
}

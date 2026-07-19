<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\StylesSheet;
use App\Models\Classroom;
use App\Models\Major;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reference sheet listing the valid values for the relation columns of the
 * siswa import template: kelas, jurusan, and hubungan orang tua.
 */
class StudentReferenceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    use StylesSheet;

    public function title(): string
    {
        return 'Referensi';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['kelas yang tersedia', 'jurusan yang tersedia', 'hubungan (orang tua)', 'jenis_kelamin'];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public function array(): array
    {
        $classrooms = Classroom::query()->orderBy('name')->pluck('name')->all();
        $majors = Major::query()->orderBy('name')->pluck('name')->all();
        $relationships = ['Ayah', 'Ibu', 'Wali'];
        $genders = ['L (Laki-laki)', 'P (Perempuan)'];

        $rows = [];
        $count = max(count($classrooms), count($majors), count($relationships), count($genders), 1);

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                $classrooms[$index] ?? null,
                $majors[$index] ?? null,
                $relationships[$index] ?? null,
                $genders[$index] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $this->styleSheet($sheet, count($this->headings()));
        $this->writeNotes($sheet, [
            'Kolom kelas wajib diisi persis sesuai daftar "kelas yang tersedia".',
            'Kolom nama, nis, dan kelas wajib diisi; NIS & NISN harus unik.',
            'Kolom tanggal_lahir menggunakan format YYYY-MM-DD, contoh 2008-03-15.',
            'Kolom orang_tua opsional — jika diisi, hubungan diisi Ayah/Ibu/Wali (kosong dianggap Wali).',
        ]);

        return [];
    }
}

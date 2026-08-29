<?php

namespace App\Exports;

use App\Exports\Concerns\StylesSheet;
use App\Models\Student;
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
 * @implements WithMapping<Student>
 */
class PointMonitoringExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StylesSheet;

    /**
     * @param  Builder<Student>  $baseQuery
     */
    public function __construct(
        private readonly Builder $baseQuery,
        private readonly string $search = '',
        private readonly string $classroomId = '',
        private readonly string $status = '',
    ) {}

    public function title(): string
    {
        return 'Rekap Poin Disiplin Siswa';
    }

    /**
     * @return Collection<int, Student>
     */
    public function collection(): Collection
    {
        return (clone $this->baseQuery)
            ->with(['classroom'])
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('nis', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->classroomId !== '', fn (Builder $query) => $query->where('classroom_id', $this->classroomId))
            ->when($this->status !== '', function (Builder $query) {
                if ($this->status === 'below_minimum') {
                    $query->where('current_point', '<=', 50);
                } elseif ($this->status === 'warning') {
                    $query->whereBetween('current_point', [51, 80]);
                } elseif ($this->status === 'safe') {
                    $query->where('current_point', '>', 80);
                }
            })
            ->orderBy('name')
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
            'sisa_poin',
            'status',
        ];
    }

    /**
     * @param  Student  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $points = $row->current_point ?? 100;

        $statusText = 'Aman';
        if ($points <= 50) {
            $statusText = 'Di Bawah Minimum';
        } elseif ($points <= 80) {
            $statusText = 'Peringatan';
        }

        return [
            $row->name,
            $row->nis,
            $row->classroom?->name,
            $points,
            $statusText,
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

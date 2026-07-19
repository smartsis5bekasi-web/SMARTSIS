<?php

namespace App\Exports;

use App\Exports\Sheets\StudentReferenceSheet;
use App\Exports\Sheets\StudentTemplateSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The downloadable siswa import template: a fill-in sheet with sample rows
 * plus a reference sheet for the kelas/jurusan/hubungan relation columns.
 */
class StudentImportTemplate implements WithMultipleSheets
{
    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [new StudentTemplateSheet, new StudentReferenceSheet];
    }
}

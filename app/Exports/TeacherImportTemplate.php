<?php

namespace App\Exports;

use App\Exports\Sheets\TeacherRoleReferenceSheet;
use App\Exports\Sheets\TeacherTemplateSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The downloadable guru import template: a fill-in sheet with sample rows
 * plus a reference sheet for the "peran" column.
 */
class TeacherImportTemplate implements WithMultipleSheets
{
    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [new TeacherTemplateSheet, new TeacherRoleReferenceSheet];
    }
}

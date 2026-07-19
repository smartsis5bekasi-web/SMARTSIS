<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait StylesSheet
{
    /**
     * Apply the shared sheet look: brand-violet header row, thin borders
     * around the data grid, and a frozen header.
     */
    protected function styleSheet(Worksheet $sheet, int $columnCount): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = max($sheet->getHighestRow(), 2);

        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '441DAA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        $sheet->freezePane('A2');
    }

    /**
     * Force text format so values like NIP/NISN/telepon keep leading zeros
     * and are not turned into scientific notation by Excel.
     *
     * @param  array<int, string>  $columns
     */
    protected function formatColumnsAsText(Worksheet $sheet, array $columns, int $throughRow): void
    {
        foreach ($columns as $column) {
            $sheet->getStyle($column.'2:'.$column.$throughRow)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /**
     * Write italic gray note lines below the data grid.
     *
     * @param  array<int, string>  $notes
     */
    protected function writeNotes(Worksheet $sheet, array $notes): void
    {
        $row = $sheet->getHighestRow() + 2;

        $sheet->setCellValue('A'.$row, 'Catatan:');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->getColor()->setRGB('6B7280');

        foreach ($notes as $index => $note) {
            $line = $row + 1 + $index;
            $sheet->setCellValue('A'.$line, '- '.$note);
            $sheet->getStyle('A'.$line)->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
        }
    }
}

<?php

namespace App\Livewire\Concerns;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Shared parsing for import modals: turns an uploaded .xlsx/CSV
 * (`$this->importFile`) into associative rows keyed by file line number.
 */
trait ImportsSpreadsheetRows
{
    /**
     * @param  array<int, string>  $requiredColumns
     * @return array{0: array<int, array<string, string|null>>, 1: string|null}
     */
    protected function parseImportFile(array $requiredColumns): array
    {
        $extension = strtolower(pathinfo((string) $this->importFile?->getClientOriginalName(), PATHINFO_EXTENSION));

        $grid = in_array($extension, ['csv', 'txt'], true) ? $this->readCsvGrid() : $this->readExcelGrid();

        if (is_string($grid)) {
            return [[], $grid];
        }

        if ($grid === []) {
            return [[], __('File kosong.')];
        }

        $header = array_map(
            fn ($cell): string => mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $cell))),
            array_shift($grid),
        );

        foreach ($requiredColumns as $column) {
            if (! in_array($column, $header, true)) {
                return [[], __('Format tidak sesuai template: kolom ":column" tidak ditemukan. Unduh template contoh terlebih dahulu.', ['column' => $column])];
            }
        }

        $rows = [];

        foreach ($grid as $index => $cells) {
            if (implode('', array_map(fn ($cell): string => trim((string) $cell), $cells)) === '') {
                continue;
            }

            $row = [];

            foreach ($header as $columnIndex => $key) {
                if ($key === '') {
                    continue;
                }

                $value = trim((string) ($cells[$columnIndex] ?? ''));
                $row[$key] = $value === '' ? null : $value;
            }

            $rows[$index + 2] = $row;
        }

        return [$rows, null];
    }

    /**
     * Read a CSV upload into a raw grid. Detects comma or semicolon
     * delimiters from the header line.
     *
     * @return array<int, array<int, string|null>>|string
     */
    private function readCsvGrid(): array|string
    {
        $handle = fopen((string) $this->importFile?->getRealPath(), 'rb');

        if ($handle === false) {
            return __('File tidak dapat dibaca.');
        }

        $firstLine = (string) fgets($handle);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $grid = [];

        while (($cells = fgetcsv($handle, null, $delimiter)) !== false) {
            $grid[] = $cells;
        }

        fclose($handle);

        return $grid;
    }

    /**
     * Read an Excel upload's first sheet into a raw grid of formatted
     * string values.
     *
     * @return array<int, array<int, string|null>>|string
     */
    private function readExcelGrid(): array|string
    {
        try {
            $spreadsheet = IOFactory::load((string) $this->importFile?->getRealPath());
        } catch (\Throwable) {
            return __('File tidak dapat dibaca, pastikan formatnya .xlsx atau CSV.');
        }

        $grid = $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        return $grid;
    }
}

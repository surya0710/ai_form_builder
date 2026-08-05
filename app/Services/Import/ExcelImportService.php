<?php

namespace App\Services\Import;

use App\Exceptions\Import\ImportException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ExcelImportService
{
    /**
     * Parse an XLSX spreadsheet into a raw form structure.
     *
     * Expected columns (case-insensitive): Label, Type, Required, Options.
     * Unknown columns are ignored. The sheet title is used as the form title
     * when present; otherwise the first non-empty cell above the header is used,
     * falling back to the worksheet name.
     *
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     *
     * @throws ImportException
     */
    public function parse(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            throw ImportException::corruptDocument($e->getMessage(), $e);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if ($rows === []) {
            throw ImportException::emptyDocument();
        }

        $headerIndex = null;
        $map = [];

        foreach ($rows as $index => $row) {
            $normalized = array_map(
                static fn ($value): string => strtolower(trim((string) $value)),
                $row,
            );

            if (in_array('label', $normalized, true)) {
                $headerIndex = $index;
                foreach ($normalized as $column => $name) {
                    if (in_array($name, ['label', 'type', 'required', 'options', 'description'], true)) {
                        $map[$name] = $column;
                    }
                }
                break;
            }
        }

        if ($headerIndex === null || ! isset($map['label'])) {
            throw ImportException::invalidStructure('Spreadsheet must include a Label column.');
        }

        $title = $this->resolveTitle($rows, $headerIndex, $sheet->getTitle());
        $fields = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $label = trim((string) ($row[$map['label']] ?? ''));

            if ($label === '') {
                continue;
            }

            $type = isset($map['type'])
                ? strtolower(trim((string) ($row[$map['type']] ?? 'text')))
                : 'text';

            $required = isset($map['required'])
                ? ($row[$map['required']] ?? false)
                : false;

            $options = isset($map['options'])
                ? ($row[$map['options']] ?? null)
                : null;

            $fields[] = [
                'label' => $label,
                'type' => $type !== '' ? $type : 'text',
                'required' => $required,
                'options' => $options,
            ];
        }

        if ($fields === []) {
            throw ImportException::emptyDocument();
        }

        return [
            'title' => $title,
            'description' => null,
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function resolveTitle(array $rows, int $headerIndex, string $sheetTitle): string
    {
        for ($i = 0; $i < $headerIndex; $i++) {
            foreach ($rows[$i] as $cell) {
                $value = trim((string) $cell);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        $sheetTitle = trim($sheetTitle);

        return $sheetTitle !== '' ? $sheetTitle : 'Imported Form';
    }
}

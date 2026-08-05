<?php

namespace App\Services\Import;

use App\Exceptions\Import\ImportException;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class WordImportService
{
    /**
     * Parse a DOCX document into a raw form structure.
     *
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     *
     * @throws ImportException
     */
    public function parse(string $path): array
    {
        try {
            $phpWord = IOFactory::load($path);
        } catch (Throwable $e) {
            throw ImportException::corruptDocument($e->getMessage(), $e);
        }

        $lines = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $this->collectLines($element, $lines);
            }
        }

        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => $line !== ''));

        if ($lines === []) {
            throw ImportException::emptyDocument();
        }

        $title = array_shift($lines);
        $fields = [];
        $current = null;

        foreach ($lines as $line) {
            if ($this->isOptionLine($line)) {
                if ($current === null) {
                    continue;
                }

                $current['options'][] = $this->optionText($line);
                $current['type'] = $current['type'] === 'text'
                    ? 'select'
                    : $current['type'];

                continue;
            }

            if ($current !== null) {
                $fields[] = $current;
            }

            $current = [
                'label' => rtrim($line, ':'),
                'type' => $this->inferType($line),
                'required' => false,
                'options' => [],
            ];
        }

        if ($current !== null) {
            $fields[] = $current;
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

    protected function collectLines(mixed $element, array &$lines): void
    {
        if ($element instanceof Text) {
            $text = trim($element->getText());

            if ($text !== '') {
                $lines[] = $text;
            }

            return;
        }

        if ($element instanceof TextRun) {
            $buffer = '';

            foreach ($element->getElements() as $child) {
                if ($child instanceof Text) {
                    $buffer .= $child->getText();
                } elseif ($child instanceof TextBreak) {
                    if (trim($buffer) !== '') {
                        $lines[] = trim($buffer);
                    }
                    $buffer = '';
                }
            }

            if (trim($buffer) !== '') {
                $lines[] = trim($buffer);
            }

            return;
        }

        if ($element instanceof AbstractContainer) {
            foreach ($element->getElements() as $child) {
                $this->collectLines($child, $lines);
            }
        }
    }

    protected function isOptionLine(string $line): bool
    {
        return (bool) preg_match('/^([-*•]|\d+[.)])\s+.+/u', $line);
    }

    protected function optionText(string $line): string
    {
        return trim((string) preg_replace('/^([-*•]|\d+[.)])\s+/u', '', $line));
    }

    protected function inferType(string $label): string
    {
        $normalized = strtolower($label);

        return match (true) {
            str_contains($normalized, 'email') => 'email',
            str_contains($normalized, 'phone'), str_contains($normalized, 'mobile') => 'phone',
            str_contains($normalized, 'url'), str_contains($normalized, 'website') => 'url',
            str_contains($normalized, 'datetime'), str_contains($normalized, 'date time') => 'datetime',
            str_contains($normalized, 'date') => 'date',
            str_contains($normalized, 'number'), str_contains($normalized, 'rating'), str_contains($normalized, 'age') => 'number',
            str_contains($normalized, 'comment'), str_contains($normalized, 'description'), str_contains($normalized, 'message'), str_contains($normalized, 'feedback') => 'textarea',
            str_contains($normalized, 'file'), str_contains($normalized, 'upload'), str_contains($normalized, 'attachment') => 'file',
            str_contains($normalized, 'agree'), str_contains($normalized, 'confirm') => 'checkbox',
            default => 'text',
        };
    }
}

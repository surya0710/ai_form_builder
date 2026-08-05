<?php

namespace App\Services\Import;

use App\Exceptions\Import\ImportException;
use Illuminate\Support\Str;

class ImportParser
{
    /**
     * Validate and normalize a parsed import structure into persistence-ready data.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     *
     * @throws ImportException
     */
    public function normalize(array $data): array
    {
        if (! array_key_exists('title', $data) || ! array_key_exists('fields', $data)) {
            throw ImportException::invalidStructure('Required keys "title" and "fields" are missing.');
        }

        $title = is_string($data['title']) ? trim($data['title']) : '';

        if ($title === '') {
            throw ImportException::invalidStructure('A non-empty form title is required.');
        }

        if (! is_array($data['fields']) || $data['fields'] === []) {
            throw ImportException::emptyDocument();
        }

        $supportedTypes = config('forms.supported_field_types', []);
        $normalizedFields = [];
        $seenNames = [];

        foreach ($data['fields'] as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = isset($field['label']) && is_string($field['label']) ? trim($field['label']) : '';

            if ($label === '') {
                continue;
            }

            $type = is_string($field['type'] ?? null) ? strtolower(trim($field['type'])) : 'text';

            if (! in_array($type, $supportedTypes, true)) {
                continue;
            }

            $name = Str::slug($label, '_') ?: 'field';
            $originalName = $name;
            $counter = 1;

            while (in_array($name, $seenNames, true)) {
                $name = $originalName.'_'.$counter;
                $counter++;
            }

            $seenNames[] = $name;

            $isRequired = $this->toBoolean($field['required'] ?? $field['is_required'] ?? false);

            $normalizedField = [
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'is_required' => $isRequired,
                'sort_order' => $index,
                'validation_rules' => $isRequired ? ['required'] : [],
                'placeholder' => is_string($field['placeholder'] ?? null) ? $field['placeholder'] : null,
                'help_text' => is_string($field['help_text'] ?? null) ? $field['help_text'] : null,
            ];

            if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
                $normalizedField['field_options'] = $this->normalizeOptions($field['options'] ?? null, $type);
            }

            $normalizedFields[] = $normalizedField;
        }

        if ($normalizedFields === []) {
            throw ImportException::emptyDocument();
        }

        return [
            'title' => $title,
            'description' => isset($data['description']) && is_string($data['description'])
                ? trim($data['description'])
                : null,
            'fields' => $normalizedFields,
        ];
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'required'], true);
    }

    /**
     * @return list<string>
     */
    protected function normalizeOptions(mixed $options, string $type): array
    {
        $defaults = $type === 'checkbox' ? ['Yes'] : ['Option 1', 'Option 2'];

        if (is_string($options)) {
            $options = preg_split('/[,;|]/', $options) ?: [];
        }

        if (! is_array($options) || $options === []) {
            return $defaults;
        }

        $normalized = [];

        foreach ($options as $option) {
            if (is_string($option) && trim($option) !== '') {
                $normalized[] = trim($option);
            }
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : $defaults;
    }
}

<?php

namespace App\Services\AI;

use App\Exceptions\AI\InvalidAIResponseException;
use Illuminate\Support\Str;

class ResponseParser
{
    /**
     * Parse and validate the raw response from the AI provider.
     *
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     *
     * @throws InvalidAIResponseException
     */
    public function parse(string $rawResponse): array
    {
        $json = $this->extractJson($rawResponse);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw InvalidAIResponseException::invalidJson(json_last_error_msg());
        }

        return $this->validateAndNormalize($data);
    }

    protected function extractJson(string $rawResponse): string
    {
        $rawResponse = trim($rawResponse);

        if ($rawResponse === '') {
            throw InvalidAIResponseException::invalidJson('Empty response.');
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $rawResponse, $matches)) {
            return trim($matches[1]);
        }

        return $rawResponse;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     */
    protected function validateAndNormalize(array $data): array
    {
        if (! array_key_exists('title', $data) || ! array_key_exists('fields', $data)) {
            throw InvalidAIResponseException::unsupportedSchema('Required keys "title" and "fields" are missing.');
        }

        $title = is_string($data['title']) ? trim($data['title']) : '';

        if ($title === '') {
            throw InvalidAIResponseException::unsupportedSchema('Title must be a non-empty string.');
        }

        if (! is_array($data['fields']) || $data['fields'] === []) {
            throw InvalidAIResponseException::unsupportedSchema('Fields must be a non-empty array.');
        }

        $supportedTypes = config('forms.supported_field_types', []);
        $optionTypes = ['select', 'radio'];
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

            $type = is_string($field['type'] ?? null) ? strtolower($field['type']) : 'text';

            if (! in_array($type, $supportedTypes, true)) {
                $type = 'text';
            }

            $name = Str::slug($label, '_') ?: 'field';
            $originalName = $name;
            $counter = 1;

            while (in_array($name, $seenNames, true)) {
                $name = $originalName.'_'.$counter;
                $counter++;
            }

            $seenNames[] = $name;

            $isRequired = (bool) ($field['required'] ?? $field['is_required'] ?? false);

            $normalizedField = [
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'is_required' => $isRequired,
                'sort_order' => $index,
                'step' => max(1, (int) ($field['step'] ?? 1)),
                'validation_rules' => $isRequired ? ['required'] : [],
                'placeholder' => is_string($field['placeholder'] ?? null) ? $field['placeholder'] : null,
                'help_text' => is_string($field['help_text'] ?? null) ? $field['help_text'] : null,
                'settings' => is_array($field['settings'] ?? null) ? $field['settings'] : [],
            ];

            if (isset($field['validation_rules']) && is_array($field['validation_rules'])) {
                $normalizedField['validation_rules'] = array_values(array_filter(array_map(
                    static fn ($rule) => is_string($rule) ? trim($rule) : '',
                    $field['validation_rules']
                )));
                if ($isRequired && ! in_array('required', $normalizedField['validation_rules'], true)) {
                    array_unshift($normalizedField['validation_rules'], 'required');
                }
            }

            if (in_array($type, [...$optionTypes, 'checkbox'], true)) {
                $normalizedField['field_options'] = $this->normalizeOptions($field['options'] ?? null, $type);
            }

            $normalizedFields[] = $normalizedField;
        }

        if ($normalizedFields === []) {
            throw InvalidAIResponseException::unsupportedSchema('No valid fields with labels could be extracted.');
        }

        return [
            'title' => $title,
            'description' => isset($data['description']) && is_string($data['description'])
                ? $data['description']
                : null,
            'fields' => $normalizedFields,
        ];
    }

    /**
     * @return list<string>
     */
    protected function normalizeOptions(mixed $options, string $type): array
    {
        $defaults = $type === 'checkbox'
            ? ['Yes']
            : ['Option 1', 'Option 2'];

        if (! is_array($options) || $options === []) {
            return $defaults;
        }

        $normalized = [];

        foreach ($options as $option) {
            if (is_string($option)) {
                $value = trim($option);

                if ($value !== '') {
                    $normalized[] = $value;
                }

                continue;
            }

            if (is_array($option)) {
                $value = $option['label'] ?? $option['value'] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $normalized[] = trim($value);
                }
            }
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : $defaults;
    }
}

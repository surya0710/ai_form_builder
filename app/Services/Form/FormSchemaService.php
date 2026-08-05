<?php

namespace App\Services\Form;

use App\Enums\FieldType;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormSchemaService
{
    public function __construct(
        protected FormBuilderService $builder,
        protected ValidationRuleCompiler $ruleCompiler,
    ) {}

    /**
     * @return array{title: string, description: string|null, settings: array<string, mixed>, fields: array<int, array<string, mixed>>}
     */
    public function export(Form $form): array
    {
        $form->loadMissing(['fields' => fn ($q) => $q->orderBy('sort_order')]);

        return [
            'title' => $form->title,
            'description' => $form->description,
            'settings' => $form->settings ?? [],
            'fields' => $form->fields->map(fn (FormField $field): array => $this->fieldToSchema($field))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{title: string, description: string|null, settings: array<string, mixed>, fields: array<int, array<string, mixed>>}
     */
    public function validateSchema(array $schema): array
    {
        if (! isset($schema['title']) || ! is_string($schema['title']) || trim($schema['title']) === '') {
            throw ValidationException::withMessages(['schema' => 'Schema title is required.']);
        }

        if (! isset($schema['fields']) || ! is_array($schema['fields'])) {
            throw ValidationException::withMessages(['schema' => 'Schema fields must be an array.']);
        }

        $supported = config('forms.supported_field_types', []);
        $names = [];
        $normalized = [];

        foreach ($schema['fields'] as $index => $field) {
            if (! is_array($field)) {
                throw ValidationException::withMessages(['schema' => "Field at index {$index} must be an object."]);
            }

            $label = isset($field['label']) && is_string($field['label']) ? trim($field['label']) : '';
            if ($label === '') {
                throw ValidationException::withMessages(['schema' => "Field at index {$index} requires a label."]);
            }

            $type = is_string($field['type'] ?? null) ? strtolower($field['type']) : '';
            if (! in_array($type, $supported, true)) {
                throw ValidationException::withMessages(['schema' => "Unsupported field type \"{$type}\" at index {$index}."]);
            }

            $name = isset($field['name']) && is_string($field['name']) && $field['name'] !== ''
                ? Str::slug($field['name'], '_')
                : (Str::slug($label, '_') ?: 'field');

            if (isset($names[$name])) {
                throw ValidationException::withMessages(['schema' => "Duplicate field key \"{$name}\"."]);
            }
            $names[$name] = true;

            $rules = $field['validation_rules'] ?? $field['validation'] ?? [];
            if (! is_array($rules)) {
                throw ValidationException::withMessages(['schema' => "validation_rules for \"{$name}\" must be an array."]);
            }

            foreach ($rules as $rule) {
                if (! is_string($rule) || trim($rule) === '') {
                    throw ValidationException::withMessages(['schema' => "Invalid validation rule on \"{$name}\"."]);
                }
            }

            $normalized[] = [
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'placeholder' => is_string($field['placeholder'] ?? null) ? $field['placeholder'] : null,
                'help_text' => is_string($field['help_text'] ?? null) ? $field['help_text'] : null,
                'default_value' => isset($field['default_value']) && (is_string($field['default_value']) || is_numeric($field['default_value']))
                    ? (string) $field['default_value']
                    : null,
                'is_required' => (bool) ($field['required'] ?? $field['is_required'] ?? false),
                'validation_rules' => array_values(array_filter(array_map('trim', $rules))),
                'field_options' => array_values(array_filter($field['options'] ?? $field['field_options'] ?? [])),
                'step' => max(1, (int) ($field['step'] ?? 1)),
                'sort_order' => $index,
                'settings' => is_array($field['settings'] ?? null) ? $field['settings'] : [],
            ];
        }

        return [
            'title' => trim($schema['title']),
            'description' => isset($schema['description']) && is_string($schema['description']) ? $schema['description'] : null,
            'settings' => is_array($schema['settings'] ?? null) ? $schema['settings'] : [],
            'fields' => $normalized,
        ];
    }

    /**
     * Replace form metadata and fields from a validated schema.
     *
     * @param  array<string, mixed>  $schema
     */
    public function apply(Form $form, array $schema): Form
    {
        $normalized = $this->validateSchema($schema);

        return DB::transaction(function () use ($form, $normalized): Form {
            $form->update([
                'title' => $normalized['title'],
                'description' => $normalized['description'],
                'settings' => array_merge($form->settings ?? [], $normalized['settings']),
            ]);

            $form->fields()->delete();

            foreach ($normalized['fields'] as $field) {
                $this->builder->addField($form, $field);
            }

            return $form->fresh(['fields']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldToSchema(FormField $field): array
    {
        $type = $field->type instanceof FieldType ? $field->type->value : (string) $field->type;

        return [
            'label' => $field->label,
            'name' => $field->name,
            'type' => $type,
            'placeholder' => $field->placeholder,
            'help_text' => $field->help_text,
            'default_value' => $field->default_value,
            'required' => (bool) $field->is_required,
            'validation_rules' => $field->validation_rules ?? [],
            'options' => $field->field_options ?? [],
            'step' => (int) ($field->step ?? 1),
            'settings' => $field->settings ?? [],
        ];
    }
}

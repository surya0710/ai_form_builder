<?php

namespace App\Services\Form;

use App\Enums\FieldType;
use App\Models\FormField;

class ValidationRuleCompiler
{
    /**
     * Expand shorthand validation tokens into Laravel-compatible rules.
     *
     * @param  list<string|mixed>  $rules
     * @return list<string>
     */
    public function compile(array $rules, ?FormField $field = null): array
    {
        $compiled = [];

        foreach ($rules as $rule) {
            if (! is_string($rule) || trim($rule) === '') {
                continue;
            }

            $rule = trim($rule);
            $lower = strtolower($rule);

            if (str_starts_with($lower, 'minlength:')) {
                $compiled[] = 'min:'.substr($rule, 10);

                continue;
            }

            if (str_starts_with($lower, 'maxlength:')) {
                $compiled[] = 'max:'.substr($rule, 10);

                continue;
            }

            if (str_starts_with($lower, 'mime:') || str_starts_with($lower, 'mimes:') || str_starts_with($lower, 'filetype:')) {
                $value = substr($rule, strpos($rule, ':') + 1);
                $compiled[] = 'mimes:'.$value;

                continue;
            }

            if (
                str_starts_with($lower, 'maxuploadsize:')
                || str_starts_with($lower, 'max_upload_size:')
                || str_starts_with($lower, 'filesize:')
            ) {
                $kb = (int) substr($rule, strpos($rule, ':') + 1);
                $compiled[] = 'max:'.$kb;

                continue;
            }

            if (str_starts_with($lower, 'regex:') && ! str_starts_with($rule, 'regex:/')) {
                $pattern = substr($rule, 6);
                $compiled[] = 'regex:/'.$pattern.'/';

                continue;
            }

            $compiled[] = $rule;
        }

        if ($field?->type === FieldType::Rating) {
            $max = (int) ($field->settings['max'] ?? 5);
            if (! collect($compiled)->contains(fn ($r) => str_starts_with((string) $r, 'integer'))) {
                $compiled[] = 'integer';
            }
            if (! collect($compiled)->contains(fn ($r) => str_starts_with((string) $r, 'min:'))) {
                $compiled[] = 'min:1';
            }
            if (! collect($compiled)->contains(fn ($r) => str_starts_with((string) $r, 'max:'))) {
                $compiled[] = 'max:'.$max;
            }
        }

        if ($field?->type === FieldType::File) {
            $maxKb = (int) config('forms.uploads.max_file_size_kb', 10240);
            $mimes = implode(',', config('forms.uploads.allowed_mimes', []));
            if ($mimes !== '' && ! collect($compiled)->contains(fn ($r) => str_starts_with((string) $r, 'mimes:'))) {
                $compiled[] = 'mimes:'.$mimes;
            }
            if (! collect($compiled)->contains(fn ($r) => str_starts_with((string) $r, 'max:'))) {
                $compiled[] = 'max:'.$maxKb;
            }
        }

        return array_values(array_unique($compiled));
    }

    /**
     * Normalize stored rules into editor-friendly keys for the builder UI.
     *
     * @param  list<string|mixed>  $rules
     * @return array{
     *     min: string,
     *     max: string,
     *     minLength: string,
     *     maxLength: string,
     *     numeric: bool,
     *     email: bool,
     *     url: bool,
     *     regex: string,
     *     fileType: string,
     *     fileSize: string
     * }
     */
    public function toEditor(array $rules): array
    {
        $editor = [
            'min' => '',
            'max' => '',
            'minLength' => '',
            'maxLength' => '',
            'numeric' => false,
            'email' => false,
            'url' => false,
            'regex' => '',
            'fileType' => '',
            'fileSize' => '',
        ];

        foreach ($rules as $rule) {
            if (! is_string($rule) || trim($rule) === '') {
                continue;
            }

            $rule = trim($rule);
            $lower = strtolower($rule);

            if ($lower === 'numeric') {
                $editor['numeric'] = true;
            } elseif ($lower === 'email') {
                $editor['email'] = true;
            } elseif ($lower === 'url') {
                $editor['url'] = true;
            } elseif (str_starts_with($lower, 'minlength:')) {
                $editor['minLength'] = substr($rule, 10);
            } elseif (str_starts_with($lower, 'maxlength:')) {
                $editor['maxLength'] = substr($rule, 10);
            } elseif (str_starts_with($lower, 'min:')) {
                $editor['min'] = substr($rule, 4);
            } elseif (str_starts_with($lower, 'max:')) {
                $editor['max'] = substr($rule, 4);
            } elseif (str_starts_with($lower, 'regex:')) {
                $pattern = substr($rule, 6);
                if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
                    $pattern = substr($pattern, 1, -1);
                }
                $editor['regex'] = $pattern;
            } elseif (
                str_starts_with($lower, 'mime:')
                || str_starts_with($lower, 'mimes:')
                || str_starts_with($lower, 'filetype:')
            ) {
                $editor['fileType'] = substr($rule, strpos($rule, ':') + 1);
            } elseif (
                str_starts_with($lower, 'maxuploadsize:')
                || str_starts_with($lower, 'max_upload_size:')
                || str_starts_with($lower, 'filesize:')
            ) {
                $editor['fileSize'] = (string) (int) substr($rule, strpos($rule, ':') + 1);
            }
        }

        return $editor;
    }

    /**
     * Build stored validation_rules from the builder editor payload.
     *
     * @param  array<string, mixed>  $editor
     * @return list<string>
     */
    public function fromEditor(array $editor): array
    {
        $rules = [];

        if (($editor['min'] ?? '') !== '' && $editor['min'] !== null) {
            $rules[] = 'min:'.$editor['min'];
        }
        if (($editor['max'] ?? '') !== '' && $editor['max'] !== null) {
            $rules[] = 'max:'.$editor['max'];
        }
        if (($editor['minLength'] ?? '') !== '' && $editor['minLength'] !== null) {
            $rules[] = 'minLength:'.$editor['minLength'];
        }
        if (($editor['maxLength'] ?? '') !== '' && $editor['maxLength'] !== null) {
            $rules[] = 'maxLength:'.$editor['maxLength'];
        }
        if (! empty($editor['numeric'])) {
            $rules[] = 'numeric';
        }
        if (! empty($editor['email'])) {
            $rules[] = 'email';
        }
        if (! empty($editor['url'])) {
            $rules[] = 'url';
        }
        if (($editor['regex'] ?? '') !== '' && $editor['regex'] !== null) {
            $rules[] = 'regex:'.$editor['regex'];
        }
        if (($editor['fileType'] ?? '') !== '' && $editor['fileType'] !== null) {
            $rules[] = 'fileType:'.$editor['fileType'];
        }
        if (($editor['fileSize'] ?? '') !== '' && $editor['fileSize'] !== null) {
            $rules[] = 'fileSize:'.$editor['fileSize'];
        }

        return $rules;
    }
}

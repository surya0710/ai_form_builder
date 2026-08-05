<?php

namespace App\Services\AI;

class PromptBuilder
{
    /**
     * Build a deterministic prompt for AI form generation.
     */
    public function build(string $userInput): string
    {
        $supportedTypes = implode(', ', $this->supportedFieldTypes());
        $schema = $this->jsonSchema();
        $constraints = $this->outputConstraints();

        return implode("\n\n", [
            'You are a form generation assistant. Produce a structured form definition from the user request.',
            'User Request: '.$userInput,
            'Supported field types: '.$supportedTypes.'. Use only these types. If a requested type does not match, use text.',
            'Required JSON schema:'."\n".$schema,
            'Output constraints:'."\n".$constraints,
        ]);
    }

    /**
     * Build a prompt to edit an existing form schema.
     */
    public function buildEdit(string $currentSchemaJson, string $userInstruction): string
    {
        $supportedTypes = implode(', ', $this->supportedFieldTypes());

        return implode("\n\n", [
            'You are a form editing assistant. Update the existing form JSON according to the user instruction.',
            'Current form schema JSON:'."\n".$currentSchemaJson,
            'User instruction: '.$userInstruction,
            'Supported field types: '.$supportedTypes.'. Use only these types. If a requested type does not match, use text.',
            'Return the full updated form JSON using this schema:'."\n".$this->jsonSchema(),
            'Output constraints:'."\n".$this->outputConstraints()."\n8. Preserve field names when editing existing fields unless renaming is requested.\n9. Preserve step numbers when present; default new fields to step 1.\n10. section fields are headings only; rating fields store an integer score.",
        ]);
    }

    /**
     * @return list<string>
     */
    protected function supportedFieldTypes(): array
    {
        return config('forms.supported_field_types', [
            'text', 'textarea', 'email', 'number', 'phone', 'url', 'date', 'datetime',
            'checkbox', 'radio', 'select', 'file', 'password', 'section', 'rating',
        ]);
    }

    protected function jsonSchema(): string
    {
        return <<<'SCHEMA'
{
  "title": "string (required, non-empty)",
  "description": "string (optional)",
  "fields": [
    {
      "label": "string (required, non-empty)",
      "type": "string (required, one of supported field types)",
      "required": "boolean (optional, default false)",
      "options": ["string"] 
    }
  ]
}
SCHEMA;
    }

    protected function outputConstraints(): string
    {
        return <<<'CONSTRAINTS'
1. Return JSON only.
2. Do not wrap the JSON in markdown code blocks.
3. Do not include explanations or any text outside the JSON object.
4. fields must be a non-empty array.
5. Every field must include label and type.
6. For select and radio fields, options must be a non-empty array of strings.
7. For checkbox fields that need choices, include an options array of strings.
CONSTRAINTS;
    }
}

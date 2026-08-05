<?php

namespace App\Http\Requests\Api;

class GenerateAIFormRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true; // Authorize in controller or policy
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}

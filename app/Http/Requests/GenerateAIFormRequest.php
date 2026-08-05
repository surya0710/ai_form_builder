<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAIFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'Please describe the form you want to generate.',
            'prompt.min' => 'Your description must be at least 3 characters.',
            'prompt.max' => 'Your description may not be longer than 1000 characters.',
        ];
    }
}

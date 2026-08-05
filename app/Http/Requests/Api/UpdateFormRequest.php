<?php

namespace App\Http\Requests\Api;

use App\Enums\FormStatus;
use Illuminate\Validation\Rule;

class UpdateFormRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true; // Handled by policy
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::enum(FormStatus::class)],
            'settings' => ['nullable', 'array'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Enums\FormStatus;
use Illuminate\Validation\Rule;

class StoreFormRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true; // Handled by policy
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(FormStatus::class)],
            'settings' => ['nullable', 'array'],
        ];
    }
}

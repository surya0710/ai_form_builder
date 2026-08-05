<?php

namespace App\Http\Requests\Api;

use App\Enums\FormStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['nullable', Rule::enum(FormStatus::class)], 'title' => ['nullable', 'string', 'max:255'], 'search' => ['nullable', 'string', 'max:255'],
            'created_from' => ['nullable', 'date'], 'created_to' => ['nullable', 'date'], 'sort' => ['nullable', Rule::in(['title', '-title', 'created_at', '-created_at', 'updated_at', '-updated_at'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}

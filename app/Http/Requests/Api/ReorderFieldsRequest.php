<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReorderFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['field_ids' => ['required', 'array'], 'field_ids.*' => ['required', 'integer', 'distinct']];
    }
}

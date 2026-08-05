<?php

namespace App\Http\Requests;

use App\Enums\FormStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'status' => ['nullable', Rule::enum(FormStatus::class)]];
    }
}

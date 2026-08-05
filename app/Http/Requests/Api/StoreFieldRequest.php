<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:255'], 'name' => ['nullable', 'alpha_dash', 'max:255'], 'type' => ['required', Rule::in(config('forms.supported_field_types'))], 'placeholder' => ['nullable', 'string', 'max:255'], 'help_text' => ['nullable', 'string'], 'default_value' => ['nullable', 'string'], 'validation_rules' => ['nullable', 'array'], 'validation_rules.*' => ['string'], 'field_options' => ['nullable', 'array'], 'field_options.*' => ['string'], 'is_required' => ['nullable', 'boolean'], 'settings' => ['nullable', 'array']];
    }
}

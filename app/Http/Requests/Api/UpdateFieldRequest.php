<?php

namespace App\Http\Requests\Api;

class UpdateFieldRequest extends StoreFieldRequest
{
    public function rules(): array
    {
        return array_map(
            static fn (array $rules): array => array_map(static fn (mixed $rule): mixed => $rule === 'required' ? 'sometimes' : $rule, $rules),
            parent::rules(),
        );
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->uuid, 'label' => $this->label, 'name' => $this->name, 'type' => $this->type->value,
            'placeholder' => $this->placeholder, 'help_text' => $this->help_text, 'default_value' => $this->default_value,
            'validation_rules' => $this->validation_rules, 'field_options' => $this->field_options, 'is_required' => $this->is_required,
            'sort_order' => $this->sort_order, 'settings' => $this->settings, 'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}

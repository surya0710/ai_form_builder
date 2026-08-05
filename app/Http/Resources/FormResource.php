<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->uuid, 'title' => $this->title, 'slug' => $this->slug, 'description' => $this->description,
            'status' => $this->status->value, 'settings' => $this->settings, 'fields' => FormFieldResource::collection($this->whenLoaded('fields')),
            'fields_count' => $this->whenCounted('fields'), 'submissions_count' => $this->whenCounted('submissions'),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}

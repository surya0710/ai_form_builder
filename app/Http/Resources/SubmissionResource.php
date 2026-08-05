<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->uuid, 'form_uuid' => $this->form?->uuid, 'submitted_by' => $this->submitted_by,
            'ip_address' => $this->ip_address, 'submitted_at' => $this->submitted_at?->toISOString(),
            'answers' => SubmissionAnswerResource::collection($this->whenLoaded('answers')), 'created_at' => $this->created_at?->toISOString()];
    }
}

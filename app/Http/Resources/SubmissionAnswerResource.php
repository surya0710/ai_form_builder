<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['field_uuid' => $this->field?->uuid, 'field_name' => $this->field?->name, 'field_label' => $this->field?->label, 'answer' => $this->answer];
    }
}

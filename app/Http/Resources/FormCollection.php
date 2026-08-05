<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FormCollection extends ResourceCollection
{
    public $collects = FormResource::class;

    public function with(Request $request): array
    {
        return ['success' => true, 'message' => 'Forms retrieved successfully.'];
    }
}

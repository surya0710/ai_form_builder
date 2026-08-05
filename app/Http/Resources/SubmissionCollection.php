<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SubmissionCollection extends ResourceCollection
{
    public $collects = SubmissionResource::class;

    public function with(Request $request): array
    {
        return ['success' => true, 'message' => 'Submissions retrieved successfully.'];
    }
}

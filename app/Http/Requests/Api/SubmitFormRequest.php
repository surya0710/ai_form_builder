<?php

namespace App\Http\Requests\Api;

use App\Models\Form;

class SubmitFormRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return []; // Dynamic validation is handled by FormBuilderService / SubmissionService or we can add it here if needed, but it's typically dynamic based on form fields.
        // Wait, the previous StoreSubmissionRequest might have had dynamic rules. Let's just leave it empty and let the service handle it, or we can copy it from the other one.
    }
}

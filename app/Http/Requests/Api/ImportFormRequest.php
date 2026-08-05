<?php

namespace App\Http\Requests\Api;

class ImportFormRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('forms.import.max_file_size_kb', 5120);
        $extensions = implode(',', config('forms.import.allowed_extensions', ['docx', 'xlsx']));

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxKb}",
                "mimes:{$extensions}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a Word (.docx) or Excel (.xlsx) file.',
            'file.mimes' => 'Unsupported file type. Please upload a .docx or .xlsx file.',
            'file.max' => 'The uploaded file is too large.',
        ];
    }
}

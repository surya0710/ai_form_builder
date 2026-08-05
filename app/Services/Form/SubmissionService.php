<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubmissionService
{
    /** @param array<string, mixed> $answers */
    public function submit(Form $form, array $answers, ?User $user = null, ?string $ipAddress = null, ?string $userAgent = null): FormSubmission
    {
        $answers = $this->validate($form, $answers);

        return DB::transaction(function () use ($form, $answers, $user, $ipAddress, $userAgent): FormSubmission {
            $submission = $form->submissions()->create(['submitted_by' => $user?->id, 'ip_address' => $ipAddress, 'user_agent' => $userAgent, 'submitted_at' => now()]);
            $this->storeAnswers($submission, $form, $answers);

            return $submission;
        });
    }

    /** @param array<string, mixed> $answers @return array<string, mixed> */
    public function validate(Form $form, array $answers): array
    {
        $rules = [];
        foreach ($form->fields as $field) {
            $fieldRules = $field->validation_rules ?? [];
            if ($field->is_required && ! in_array('required', $fieldRules, true)) {
                array_unshift($fieldRules, 'required');
            }
            $rules[$field->name] = $fieldRules ?: ['nullable'];
        }

        return Validator::make($answers, $rules)->validate();
    }

    /** @param array<string, mixed> $answers */
    public function storeAnswers(FormSubmission $submission, Form $form, array $answers): void
    {
        foreach ($form->fields as $field) {
            $answer = $answers[$field->name] ?? null;
            if ($answer instanceof UploadedFile) {
                $answer = $answer->store('form-submissions', config('forms.uploads.disk'));
            }
            $submission->answers()->create(['field_id' => $field->id, 'answer' => is_array($answer) ? json_encode($answer, JSON_THROW_ON_ERROR) : $answer]);
        }
    }
}

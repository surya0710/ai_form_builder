<?php

namespace App\Services\Form;

use App\Enums\FieldType;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionService
{
    public function __construct(protected ValidationRuleCompiler $ruleCompiler) {}

    /** @param array<string, mixed> $answers */
    public function submit(Form $form, array $answers, ?User $user = null, ?string $ipAddress = null, ?string $userAgent = null): FormSubmission
    {
        $answers = $this->validate($form, $answers);

        return DB::transaction(function () use ($form, $answers, $user, $ipAddress, $userAgent): FormSubmission {
            $submission = $form->submissions()->create([
                'submitted_by' => $user?->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'submitted_at' => now(),
            ]);
            $this->storeAnswers($submission, $form, $answers);

            return $submission;
        });
    }

    /** @param array<string, mixed> $answers @return array<string, mixed> */
    public function validate(Form $form, array $answers, ?int $step = null): array
    {
        $rules = [];
        $fields = $form->fields->filter(function ($field) use ($step) {
            $type = $field->type instanceof FieldType ? $field->type : FieldType::tryFrom((string) $field->type);

            if ($type === FieldType::Section) {
                return false;
            }

            if ($step !== null && (int) $field->step !== $step) {
                return false;
            }

            return true;
        });

        foreach ($fields as $field) {
            $fieldRules = $this->ruleCompiler->compile($field->validation_rules ?? [], $field);
            if ($field->is_required && ! in_array('required', $fieldRules, true)) {
                array_unshift($fieldRules, 'required');
            }
            $rules[$field->name] = $fieldRules !== [] ? $fieldRules : ['nullable'];
        }

        return Validator::make($answers, $rules)->validate();
    }

    /** @param array<string, mixed> $answers */
    public function storeAnswers(FormSubmission $submission, Form $form, array $answers): void
    {
        foreach ($form->fields as $field) {
            $type = $field->type instanceof FieldType ? $field->type : FieldType::tryFrom((string) $field->type);
            if ($type === FieldType::Section) {
                continue;
            }

            $answer = $answers[$field->name] ?? null;
            if ($answer instanceof UploadedFile) {
                $answer = $answer->store('form-submissions', config('forms.uploads.disk'));
            }
            $submission->answers()->create([
                'field_id' => $field->id,
                'answer' => is_array($answer) ? json_encode($answer, JSON_THROW_ON_ERROR) : $answer,
            ]);
        }
    }

    public function delete(FormSubmission $submission): void
    {
        $submission->delete();
    }

    public function exportCsv(Form $form): StreamedResponse
    {
        $form->load(['fields' => fn ($q) => $q->orderBy('sort_order'), 'submissions.answers']);
        $inputFields = $form->fields->filter(function ($field) {
            $type = $field->type instanceof FieldType ? $field->type : FieldType::tryFrom((string) $field->type);

            return $type !== FieldType::Section;
        });

        $filename = (Str::slug($form->slug) ?: 'form').'-submissions-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($form, $inputFields): void {
            $handle = fopen('php://output', 'w');
            $headers = ['submission_uuid', 'submitted_at', ...$inputFields->pluck('name')->all()];
            fputcsv($handle, $headers);

            foreach ($form->submissions()->orderBy('submitted_at')->with('answers')->cursor() as $submission) {
                $answersByField = $submission->answers->keyBy('field_id');
                $row = [
                    $submission->uuid,
                    optional($submission->submitted_at)?->toDateTimeString(),
                ];
                foreach ($inputFields as $field) {
                    $row[] = $answersByField->get($field->id)?->answer;
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

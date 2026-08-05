<?php

namespace Tests\Feature;

use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\SubmissionAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_form_can_be_created_with_a_uuid_and_enum_status(): void
    {
        $form = Form::factory()->create(['status' => FormStatus::Draft]);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'uuid' => $form->uuid,
            'status' => FormStatus::Draft->value,
        ]);
        $this->assertNotEmpty($form->uuid);
        $this->assertSame(FormStatus::Draft, $form->status);
    }

    public function test_forms_can_be_listed_for_their_owner(): void
    {
        $user = User::factory()->create();
        Form::factory()->count(2)->for($user)->create();
        Form::factory()->create();

        $this->assertCount(2, $user->forms()->get());
    }

    public function test_form_domain_relationships_are_configured(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();
        $field = FormField::query()->create([
            'form_id' => $form->id,
            'label' => 'Email',
            'name' => 'email',
            'type' => FieldType::Email,
            'is_required' => true,
            'sort_order' => 0,
        ]);
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $answer = SubmissionAnswer::query()->create([
            'submission_id' => $submission->id,
            'field_id' => $field->id,
            'answer' => 'ada@example.test',
        ]);

        $this->assertTrue($form->user->is($user));
        $this->assertTrue($field->form->is($form));
        $this->assertTrue($submission->form->is($form));
        $this->assertTrue($submission->user->is($user));
        $this->assertTrue($answer->submission->is($submission));
        $this->assertTrue($answer->field->is($field));
        $this->assertCount(1, $form->fields);
        $this->assertCount(1, $form->submissions);
        $this->assertCount(1, $field->answers);
        $this->assertCount(1, $submission->answers);
    }
}

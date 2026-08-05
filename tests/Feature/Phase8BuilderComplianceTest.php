<?php

namespace Tests\Feature;

use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Jobs\EditAiFormJob;
use App\Jobs\GenerateAiFormJob;
use App\Livewire\Builder\Builder;
use App\Livewire\Forms\GenerateForm;
use App\Livewire\Forms\Submissions;
use App\Livewire\Public\FormRenderer;
use App\Models\Form;
use App\Models\User;
use App\Services\AI\Providers\AIProviderInterface;
use App\Services\Form\FormBuilderService;
use App\Services\Form\FormSchemaService;
use App\Services\Form\SubmissionService;
use App\Services\Form\ValidationRuleCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class Phase8BuilderComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_supports_drag_reorder_duplicate_section_and_rating(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();
        $builder = app(FormBuilderService::class);
        $a = $builder->addField($form, ['label' => 'Name', 'type' => FieldType::Text]);
        $b = $builder->addField($form, ['label' => 'Score', 'type' => FieldType::Rating, 'step' => 2]);
        $section = $builder->addField($form, ['label' => 'Personal Info', 'type' => FieldType::Section, 'step' => 1]);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->assertSee('Field palette')
            ->assertSee('Form canvas')
            ->assertSee('Section Heading')
            ->assertSee('Raw JSON')
            ->call('reorder', [$b->id, $section->id, $a->id])
            ->call('duplicateField', $a->id)
            ->assertSee('Edit with AI');

        $this->assertSame(
            [$b->id, $section->id, $a->id, $form->fields()->where('label', 'Name Copy')->value('id')],
            $form->fields()->orderBy('sort_order')->pluck('id')->all()
        );
        $this->assertDatabaseHas('form_fields', ['form_id' => $form->id, 'type' => 'section', 'label' => 'Personal Info']);
        $this->assertDatabaseHas('form_fields', ['form_id' => $form->id, 'type' => 'rating', 'label' => 'Score']);
    }

    public function test_palette_click_adds_field_and_drop_inserts_at_index(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();
        $existing = app(FormBuilderService::class)->addField($form, ['label' => 'First', 'type' => FieldType::Text]);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->call('addFieldByType', 'email')
            ->call('addFieldByType', 'phone', 0)
            ->assertSee('Field added');

        $ordered = $form->fresh()->fields()->orderBy('sort_order')->get();
        $this->assertCount(3, $ordered);
        $this->assertSame('phone', $ordered[0]->type->value);
        $this->assertSame($existing->id, $ordered[1]->id);
        $this->assertSame('email', $ordered[2]->type->value);
    }

    public function test_inline_key_and_validation_editor(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();
        $field = app(FormBuilderService::class)->addField($form, ['label' => 'Website', 'type' => FieldType::Text]);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->call('updateInline', $field->id, 'name', 'website_url')
            ->call('updateInline', $field->id, 'step', 2)
            ->call('updateValidationEditor', $field->id, [
                'min' => '',
                'max' => '',
                'minLength' => '3',
                'maxLength' => '100',
                'numeric' => false,
                'email' => false,
                'url' => true,
                'regex' => '',
                'fileType' => '',
                'fileSize' => '',
            ]);

        $field->refresh();
        $this->assertSame('website_url', $field->name);
        $this->assertSame(2, (int) $field->step);
        $this->assertContains('minLength:3', $field->validation_rules);
        $this->assertContains('maxLength:100', $field->validation_rules);
        $this->assertContains('url', $field->validation_rules);
    }

    public function test_only_one_field_expands_at_a_time(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();
        $builder = app(FormBuilderService::class);
        $name = $builder->addField($form, ['label' => 'Name', 'type' => FieldType::Text]);
        $email = $builder->addField($form, ['label' => 'Email', 'type' => FieldType::Email]);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->assertSet('expandedFieldId', null)
            ->assertDontSee('Placeholder')
            ->call('selectField', $name->id)
            ->assertSet('expandedFieldId', $name->id)
            ->assertSee('Placeholder')
            ->call('selectField', $email->id)
            ->assertSet('expandedFieldId', $email->id)
            ->call('collapseField')
            ->assertSet('expandedFieldId', null);
    }

    public function test_schema_editor_two_way_sync_and_validation(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['title' => 'Old']);
        app(FormBuilderService::class)->addField($form, ['label' => 'Email', 'type' => FieldType::Email]);

        $schema = [
            'title' => 'New Title',
            'description' => 'Updated',
            'fields' => [
                ['label' => 'Full Name', 'type' => 'text', 'required' => true, 'step' => 1],
                ['label' => 'Rating', 'type' => 'rating', 'step' => 2],
            ],
        ];

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->call('switchTab', 'schema')
            ->set('schemaJson', json_encode($schema))
            ->call('applySchema')
            ->assertSet('tab', 'canvas')
            ->assertSee('New Title');

        $form->refresh();
        $this->assertSame('New Title', $form->title);
        $this->assertCount(2, $form->fields);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->set('schemaJson', '{not-json')
            ->call('applySchema')
            ->assertSet('errorMessage', 'Invalid JSON. Please fix the schema and try again.');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FormSchemaService::class)->validateSchema([
            'title' => 'X',
            'fields' => [
                ['label' => 'A', 'type' => 'text', 'name' => 'dup'],
                ['label' => 'B', 'type' => 'text', 'name' => 'dup'],
            ],
        ]);
    }

    public function test_schema_rejects_unsupported_field_type(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FormSchemaService::class)->validateSchema([
            'title' => 'X',
            'fields' => [
                ['label' => 'Weird', 'type' => 'magic_widget'],
            ],
        ]);
    }

    public function test_validation_rule_compiler_expands_shorthand(): void
    {
        $compiler = app(ValidationRuleCompiler::class);
        $rules = $compiler->compile(['minLength:3', 'maxLength:10', 'mime:pdf,docx', 'maxUploadSize:2048', 'regex:^[A-Z]+$', 'fileType:png', 'fileSize:512']);

        $this->assertContains('min:3', $rules);
        $this->assertContains('max:10', $rules);
        $this->assertContains('mimes:pdf,docx', $rules);
        $this->assertContains('max:2048', $rules);
        $this->assertContains('regex:/^[A-Z]+$/', $rules);
        $this->assertContains('mimes:png', $rules);
        $this->assertContains('max:512', $rules);
    }

    public function test_multi_step_public_form_navigation(): void
    {
        $form = Form::factory()->create(['status' => FormStatus::Published, 'title' => 'Multi']);
        $builder = app(FormBuilderService::class);
        $builder->addField($form, ['label' => 'Name', 'type' => FieldType::Text, 'is_required' => true, 'step' => 1]);
        $builder->addField($form, ['label' => 'Score', 'type' => FieldType::Rating, 'is_required' => true, 'step' => 2]);

        Livewire::test(FormRenderer::class, ['uuid' => $form->uuid])
            ->assertSee('Step 1 of 2')
            ->assertSee('Name')
            ->set('answers.name', 'Ada')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertSee('Score')
            ->call('previousStep')
            ->assertSet('currentStep', 1);
    }

    public function test_submissions_list_and_csv_export(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['status' => FormStatus::Published]);
        $field = app(FormBuilderService::class)->addField($form, ['label' => 'Name', 'type' => FieldType::Text]);
        app(SubmissionService::class)->submit($form->fresh('fields'), ['name' => 'Ada Lovelace'], null, '127.0.0.1');

        $submission = $form->submissions()->first();

        Livewire::actingAs($user)
            ->test(Submissions::class, ['form' => $form])
            ->assertSee($submission->uuid)
            ->assertSee('Export CSV')
            ->assertSee('1 answers');

        $csv = app(SubmissionService::class)->exportCsv($form->fresh(['fields']));
        ob_start();
        $csv->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString('Ada Lovelace', $content);
        $this->assertStringContainsString('submission_uuid', $content);
    }

    public function test_ai_edit_preview_and_apply(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['title' => 'Base']);
        app(FormBuilderService::class)->addField($form, ['label' => 'Phone', 'type' => FieldType::Phone]);

        $this->mock(AIProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->once()->andReturn([
                'content' => json_encode([
                    'title' => 'Updated Form',
                    'description' => null,
                    'fields' => [
                        ['label' => 'Phone', 'type' => 'phone', 'required' => true],
                        ['label' => 'Emergency Contact', 'type' => 'section'],
                        ['label' => 'Contact Name', 'type' => 'text', 'required' => true],
                    ],
                ]),
                'tokens' => 12,
                'model' => 'gpt-4o-mini',
            ]);
        });

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->call('openAiPanel')
            ->set('aiPrompt', 'Add emergency contact section. Make phone required.')
            ->call('generateAiEdit')
            ->assertSet('aiPreview.title', 'Updated Form')
            ->assertSet('aiGenerating', false)
            ->call('applyAiEdit')
            ->assertSee('AI changes applied');

        $this->assertDatabaseHas('forms', ['id' => $form->id, 'title' => 'Updated Form']);
        $this->assertDatabaseHas('form_fields', ['form_id' => $form->id, 'type' => 'section', 'label' => 'Emergency Contact']);
        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'form_id' => $form->id,
            'mode' => 'edit',
            'status' => 'completed',
        ]);
    }

    public function test_ai_edit_dispatches_queue_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->call('openAiPanel')
            ->set('aiPrompt', 'Make all fields required')
            ->call('generateAiEdit')
            ->assertSet('aiQueueStatus', 'queued');

        Queue::assertPushed(EditAiFormJob::class);
    }

    public function test_ai_generation_dispatches_queue_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'Create a feedback form')
            ->call('generate')
            ->assertSet('queueStatus', 'queued');

        Queue::assertPushed(GenerateAiFormJob::class);
    }

    public function test_queued_ai_generation_completes_with_sync_driver(): void
    {
        $user = User::factory()->create();

        $this->mock(AIProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->once()->andReturn([
                'content' => json_encode([
                    'title' => 'Queued Form',
                    'fields' => [
                        ['label' => 'Name', 'type' => 'text'],
                    ],
                ]),
                'tokens' => 9,
                'model' => 'gpt-4o-mini',
            ]);
        });

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'Create a queued form')
            ->call('generate')
            ->assertSet('step', 'preview')
            ->assertSee('Queued Form')
            ->assertSee('Completed');

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'status' => 'completed',
            'prompt' => 'Create a queued form',
        ]);
    }
}

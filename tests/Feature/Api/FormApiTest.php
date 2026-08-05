<?php

namespace Tests\Feature\Api;

use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use App\Services\Form\FormBuilderService;
use App\Services\Form\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_a_sanctum_token_for_private_routes(): void
    {
        $this->getJson('/api/v1/forms')->assertUnauthorized()->assertJson(['success' => false, 'message' => 'Unauthenticated.']);
        $user = User::factory()->create();
        $token = $user->createToken('tests')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/forms')->assertOk()->assertJsonPath('success', true);
    }

    public function test_form_crud_uses_resources_and_validation_envelopes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('tests')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/forms', [])->assertUnprocessable()->assertJsonPath('message', 'Validation failed.')->assertJsonStructure(['errors' => ['title']]);
        $created = $this->withToken($token)->postJson('/api/v1/forms', ['title' => 'Employee Survey', 'description' => 'Annual survey'])->assertCreated()->assertJsonPath('success', true);
        $uuid = $created->json('data.uuid');
        $this->withToken($token)->getJson("/api/v1/forms/{$uuid}")->assertOk()->assertJsonPath('data.title', 'Employee Survey')->assertJsonStructure(['data' => ['fields']]);
        $this->withToken($token)->putJson("/api/v1/forms/{$uuid}", ['title' => 'Updated Survey', 'status' => 'published'])->assertOk()->assertJsonPath('data.status', 'published');
        $this->withToken($token)->deleteJson("/api/v1/forms/{$uuid}")->assertOk()->assertJsonPath('success', true);
    }

    public function test_form_list_supports_pagination_filters_search_and_sorting(): void
    {
        $user = User::factory()->create();
        Form::factory()->for($user)->create(['title' => 'Employee Feedback', 'status' => FormStatus::Published]);
        Form::factory()->for($user)->create(['title' => 'Customer Survey', 'status' => FormStatus::Draft]);
        $token = $user->createToken('tests')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/forms?status=published&search=employee&sort=title&per_page=1')->assertOk()->assertJsonPath('success', true)->assertJsonPath('meta.per_page', 1)->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Employee Feedback');
    }

    public function test_field_endpoints_and_owner_authorization_work(): void
    {
        $owner = User::factory()->create();
        $form = Form::factory()->for($owner)->create();
        $token = $owner->createToken('tests')->plainTextToken;
        $field = $this->withToken($token)->postJson("/api/v1/forms/{$form->uuid}/fields", ['label' => 'Email', 'type' => 'email', 'is_required' => true, 'validation_rules' => ['email']])->assertCreated()->json('data');
        $this->withToken($token)->putJson("/api/v1/fields/{$field['uuid']}", ['label' => 'Work Email'])->assertOk()->assertJsonPath('data.label', 'Work Email');
        $this->withToken($token)->postJson("/api/v1/fields/{$field['uuid']}/duplicate")->assertCreated()->assertJsonPath('success', true);
        $ids = $form->fields()->pluck('id')->all();
        $this->withToken($token)->postJson("/api/v1/forms/{$form->uuid}/reorder-fields", ['field_ids' => array_reverse($ids)])->assertOk();
        $this->withToken($token)->deleteJson("/api/v1/fields/{$field['uuid']}")->assertOk();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/forms/{$form->uuid}")->assertForbidden()->assertJsonPath('message', 'Forbidden.');
    }

    public function test_public_api_only_exposes_published_forms_and_stores_submissions(): void
    {
        $form = Form::factory()->create(['status' => FormStatus::Published]);
        $field = app(FormBuilderService::class)->addField($form, ['label' => 'Email', 'type' => FieldType::Email, 'is_required' => true, 'validation_rules' => ['email']]);
        $this->getJson("/api/v1/public/forms/{$form->uuid}")->assertOk()->assertJsonPath('data.uuid', $form->uuid);
        $this->postJson("/api/v1/public/forms/{$form->uuid}/submit", [$field->name => 'invalid'])->assertUnprocessable()->assertJsonPath('message', 'Validation failed.');
        $this->postJson("/api/v1/public/forms/{$form->uuid}/submit", [$field->name => 'test@example.test'])->assertCreated()->assertJsonPath('data.answers.0.answer', 'test@example.test');
        $draft = Form::factory()->create(['status' => FormStatus::Draft]);
        $this->getJson("/api/v1/public/forms/{$draft->uuid}")->assertNotFound();
    }

    public function test_only_owner_can_read_and_delete_submissions(): void
    {
        $owner = User::factory()->create();
        $form = Form::factory()->for($owner)->create(['status' => FormStatus::Published]);
        $field = app(FormBuilderService::class)->addField($form, ['label' => 'Name', 'type' => FieldType::Text]);
        $submission = app(SubmissionService::class)->submit($form->load('fields'), [$field->name => 'Ada']);
        $token = $owner->createToken('tests')->plainTextToken;
        $this->withToken($token)->getJson("/api/v1/forms/{$form->uuid}/submissions")->assertOk()->assertJsonPath('success', true)->assertJsonPath('meta.total', 1);
        $this->withToken($token)->getJson("/api/v1/submissions/{$submission->uuid}")->assertOk()->assertJsonPath('data.uuid', $submission->uuid);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/submissions/{$submission->uuid}")->assertForbidden();
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/submissions/{$submission->uuid}")->assertOk();
    }
}

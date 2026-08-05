<?php

namespace Tests\Feature;

use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use App\Services\Form\FormBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormBuilderFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_create_update_and_delete_a_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('forms.store'), ['title' => 'Customer Survey', 'description' => 'Tell us what you think.'])->assertRedirect();
        $form = Form::query()->firstOrFail();
        $this->assertSame('customer-survey', $form->slug);
        $this->actingAs($user)->put(route('forms.update', $form), ['title' => 'Updated Survey', 'description' => 'Updated', 'status' => 'draft'])->assertRedirect(route('forms.show', $form));
        $this->assertDatabaseHas('forms', ['id' => $form->id, 'title' => 'Updated Survey']);
        $this->actingAs($user)->delete(route('forms.destroy', $form))->assertRedirect(route('forms.index'));
        $this->assertDatabaseMissing('forms', ['id' => $form->id]);
    }

    public function test_field_crud_reordering_and_duplicate_name_protection_work(): void
    {
        $form = Form::factory()->create();
        $builder = app(FormBuilderService::class);
        $first = $builder->addField($form, ['label' => 'Email', 'type' => FieldType::Email, 'validation_rules' => ['email']]);
        $second = $builder->addField($form, ['label' => 'Email', 'type' => FieldType::Text]);
        $this->assertSame('email', $first->name);
        $this->assertSame('email_2', $second->name);
        $updated = $builder->updateField($first, ['label' => 'Work email', 'name' => 'work_email', 'type' => FieldType::Email, 'is_required' => true]);
        $this->assertTrue($updated->is_required);
        $builder->reorderFields($form, [$second->id, $first->id]);
        $this->assertSame([$second->id, $first->id], $form->fields()->orderBy('sort_order')->pluck('id')->all());
        $builder->deleteField($second);
        $this->assertDatabaseMissing('form_fields', ['id' => $second->id]);
    }

    public function test_owner_can_publish_and_archive_a_form(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();
        $this->actingAs($user)->post(route('forms.publish', $form))->assertSessionHas('status', 'Form published.');
        $this->assertSame(FormStatus::Published, $form->refresh()->status);
        $this->actingAs($user)->post(route('forms.archive', $form))->assertSessionHas('status', 'Form archived.');
        $this->assertSame(FormStatus::Archived, $form->refresh()->status);
    }

    public function test_builder_can_publish_form(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['status' => FormStatus::Draft]);

        $this->actingAs($user)
            ->get(route('forms.builder', $form))
            ->assertOk()
            ->assertSee('Publish')
            ->assertSee('Field palette')
            ->assertSee('Esc collapses');

        \Livewire\Livewire::test(\App\Livewire\Builder\Builder::class, ['form' => $form])
            ->call('publishForm')
            ->assertSet('form.status', FormStatus::Published)
            ->assertSet('showPublishModal', true)
            ->assertSee('Form Published Successfully')
            ->assertSee('Copy Link')
            ->assertSee('Copy Embed');
    }

    public function test_publish_modal_exposes_public_url_and_embed_code(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create(['status' => FormStatus::Draft, 'title' => 'Share Me']);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Builder\Builder::class, ['form' => $form])
            ->call('publishForm')
            ->assertSee(route('public.forms.show', $form->fresh()->uuid), false)
            ->assertSee('&lt;iframe', false)
            ->assertSee('/api/v1/public/forms/'.$form->fresh()->uuid, false)
            ->assertSee('data-form=&quot;'.$form->fresh()->uuid.'&quot;', false);

        $form->refresh();
        $this->assertSame(FormStatus::Published, $form->status);
        $this->assertNotEmpty($form->settings['published_at'] ?? null);
        $this->assertStringContainsString($form->uuid, $form->publicUrl());
        $this->assertStringContainsString('<iframe', $form->embedCode());
        $this->assertStringContainsString('height="600"', $form->embedCode('100%', 600));
    }

    public function test_published_forms_render_publicly_but_drafts_do_not(): void
    {
        $published = Form::factory()->create(['status' => FormStatus::Published, 'title' => 'Public Survey']);
        app(FormBuilderService::class)->addField($published, ['label' => 'Name', 'type' => FieldType::Text, 'is_required' => true]);
        $this->get(route('public.forms.show', $published->uuid))->assertOk()->assertSee('Public Survey')->assertSee('Name');
        $draft = Form::factory()->create(['status' => FormStatus::Draft]);
        $this->get(route('public.forms.show', $draft->uuid))->assertNotFound();
    }

    public function test_owners_can_preview_unpublished_forms(): void
    {
        $owner = User::factory()->create();
        $draft = Form::factory()->for($owner)->create(['status' => FormStatus::Draft, 'title' => 'Draft Preview']);
        app(FormBuilderService::class)->addField($draft, ['label' => 'Company', 'type' => FieldType::Text, 'is_required' => true]);
        app(FormBuilderService::class)->addField($draft, ['label' => 'Mobile Number', 'type' => FieldType::Phone, 'is_required' => true]);

        $this->actingAs($owner)
            ->get(route('public.forms.show', $draft->uuid))
            ->assertOk()
            ->assertSee('Draft Preview')
            ->assertSee('Preview only')
            ->assertSee('Company')
            ->assertSee('Mobile Number')
            ->assertSee('type="tel"', false);

        $this->actingAs(User::factory()->create())
            ->get(route('public.forms.show', $draft->uuid))
            ->assertNotFound();
    }

    public function test_builder_can_save_field_settings_with_enum_type(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->for($user)->create();
        $field = app(FormBuilderService::class)->addField($form, [
            'label' => 'Mobile Number',
            'type' => FieldType::Phone,
        ]);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Builder\Builder::class, ['form' => $form])
            ->call('updateInline', $field->id, 'label', 'Phone Number')
            ->call('updateInline', $field->id, 'placeholder', '+1 555 0100')
            ->assertSet('statusMessage', 'Field updated.');

        $this->assertDatabaseHas('form_fields', [
            'id' => $field->id,
            'label' => 'Phone Number',
            'type' => 'phone',
            'placeholder' => '+1 555 0100',
        ]);
    }

    public function test_public_submission_stores_answers_and_enforces_dynamic_rules(): void
    {
        $form = Form::factory()->create(['status' => FormStatus::Published]);
        $field = app(FormBuilderService::class)->addField($form, ['label' => 'Email', 'type' => FieldType::Email, 'is_required' => true, 'validation_rules' => ['email', 'max:255']]);
        $this->post(route('public.forms.submit', $form->uuid), [$field->name => 'not-an-email'])->assertSessionHasErrors($field->name);
        $this->post(route('public.forms.submit', $form->uuid), [$field->name => 'person@example.test'])->assertRedirect(route('public.forms.show', $form->uuid));
        $this->assertDatabaseHas('form_submissions', ['form_id' => $form->id]);
        $this->assertDatabaseHas('submission_answers', ['field_id' => $field->id, 'answer' => 'person@example.test']);
    }

    public function test_non_owners_cannot_edit_forms(): void
    {
        $form = Form::factory()->create();
        $this->actingAs(User::factory()->create())->get(route('forms.edit', $form))->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('forms.update', $form), ['title' => 'Nope'])->assertForbidden();
    }
}

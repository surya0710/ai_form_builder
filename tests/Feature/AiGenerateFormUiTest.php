<?php

namespace Tests\Feature;

use App\Enums\FormStatus;
use App\Exceptions\AI\AIProviderException;
use App\Livewire\Forms\GenerateForm;
use App\Models\Form;
use App\Models\User;
use App\Services\AI\Providers\AIProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class AiGenerateFormUiTest extends TestCase
{
    use RefreshDatabase;

    private function mockProvider(callable $generate): MockInterface
    {
        return $this->mock(AIProviderInterface::class, function (MockInterface $mock) use ($generate): void {
            $mock->shouldReceive('generate')->once()->andReturnUsing($generate);
        });
    }

    private function providerPayload(array $form): array
    {
        return [
            'content' => json_encode($form),
            'tokens' => 21,
            'model' => 'gpt-4o-mini',
        ];
    }

    public function test_authenticated_user_can_open_ai_generation_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forms.ai'))
            ->assertOk()
            ->assertSee('Generate Form using AI')
            ->assertSee('Employee Feedback Form')
            ->assertSee('Customer Survey');
    }

    public function test_guests_cannot_open_ai_generation_page(): void
    {
        $this->get(route('forms.ai'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_and_index_link_to_ai_generation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('forms.ai'), false)
            ->assertSee('Generate with AI');

        $this->actingAs($user)
            ->get(route('forms.index'))
            ->assertOk()
            ->assertSee(route('forms.ai'), false);
    }

    public function test_prompt_validation_requires_minimum_length(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'ab')
            ->call('generate')
            ->assertHasErrors(['prompt' => 'min']);
    }

    public function test_prompt_validation_rejects_empty_and_too_long(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', '')
            ->call('generate')
            ->assertHasErrors(['prompt' => 'required']);

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', str_repeat('a', 1001))
            ->call('generate')
            ->assertHasErrors(['prompt' => 'max']);
    }

    public function test_generate_shows_preview_without_saving_form(): void
    {
        $user = User::factory()->create();

        $this->mockProvider(fn () => $this->providerPayload([
            'title' => 'Onboarding Form',
            'description' => 'Welcome new hires',
            'fields' => [
                ['label' => 'Department', 'type' => 'select', 'options' => ['HR', 'Engineering']],
                ['label' => 'Joining Date', 'type' => 'date', 'required' => true],
            ],
        ]));

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'Create an employee onboarding form')
            ->call('generate')
            ->assertSet('step', 'preview')
            ->assertSee('Onboarding Form')
            ->assertSee('Welcome new hires')
            ->assertSee('Department')
            ->assertSee('Joining Date')
            ->assertSee('Save Form')
            ->assertSee('Regenerate');

        $this->assertDatabaseCount('forms', 0);
        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'status' => 'completed',
            'prompt' => 'Create an employee onboarding form',
        ]);
    }

    public function test_save_generated_form_redirects_to_builder(): void
    {
        $user = User::factory()->create();

        $this->mockProvider(fn () => $this->providerPayload([
            'title' => 'Customer Survey',
            'description' => null,
            'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => true],
                ['label' => 'Rating', 'type' => 'radio', 'options' => ['1', '2', '3']],
            ],
        ]));

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'Create a customer survey')
            ->call('generate')
            ->call('saveForm')
            ->assertRedirect(route('forms.builder', Form::query()->where('title', 'Customer Survey')->firstOrFail()));

        $form = Form::query()->where('title', 'Customer Survey')->firstOrFail();

        $this->assertSame($user->id, $form->user_id);
        $this->assertSame(FormStatus::Draft, $form->status);
        $this->assertCount(2, $form->fields);
    }

    public function test_handles_ai_unavailable_with_friendly_message(): void
    {
        $user = User::factory()->create();

        $this->mock(AIProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(AIProviderException::unavailable('Server Error'));
        });

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'Create a test form')
            ->call('generate')
            ->assertSet('step', 'prompt')
            ->assertSet('errorMessage', 'AI provider is currently unavailable. Please try again later.')
            ->assertSee('AI provider is currently unavailable');
    }

    public function test_handles_missing_api_key_with_friendly_message(): void
    {
        $user = User::factory()->create();

        $this->mock(AIProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(AIProviderException::missingApiKey());
        });

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'Create a test form')
            ->call('generate')
            ->assertSet('errorMessage', 'AI generation is not configured. Please contact the administrator.');
    }

    public function test_handles_timeout_with_friendly_message(): void
    {
        $user = User::factory()->create();

        $this->mock(AIProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(AIProviderException::timeout());
        });

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->set('prompt', 'Create a test form')
            ->call('generate')
            ->assertSet('errorMessage', 'AI provider request timed out. Please try again.');
    }

    public function test_example_prompt_fills_textarea(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GenerateForm::class)
            ->call('useExample', 'Job Application')
            ->assertSet('prompt', 'Create a Job Application with relevant fields.');
    }
}

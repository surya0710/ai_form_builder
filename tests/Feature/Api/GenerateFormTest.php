<?php

namespace Tests\Feature\Api;

use App\Exceptions\AI\AIProviderException;
use App\Models\User;
use App\Services\AI\Providers\AIProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class GenerateFormTest extends TestCase
{
    use RefreshDatabase;

    private function mockProvider(callable $generate): MockInterface
    {
        return $this->mock(AIProviderInterface::class, function (MockInterface $mock) use ($generate): void {
            $mock->shouldReceive('generate')->once()->andReturnUsing($generate);
        });
    }

    private function providerPayload(array $form, ?string $model = 'gpt-4o-mini'): array
    {
        return [
            'content' => json_encode($form),
            'tokens' => 21,
            'model' => $model,
        ];
    }

    public function test_can_generate_form_from_prompt(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mockProvider(fn () => $this->providerPayload([
            'title' => 'Test Form',
            'description' => 'Test Description',
            'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => true],
                ['label' => 'Role', 'type' => 'select', 'options' => ['Admin', 'User']],
            ],
        ]));

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Test Form')
            ->assertJsonPath('data.description', 'Test Description')
            ->assertJsonCount(2, 'data.fields');

        $this->assertDatabaseHas('forms', [
            'title' => 'Test Form',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('form_fields', [
            'label' => 'Role',
            'type' => 'select',
        ]);

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'status' => 'success',
            'prompt' => 'Create a test form',
            'model' => 'gpt-4o-mini',
        ]);
    }

    public function test_handles_invalid_json_from_provider(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mockProvider(fn () => [
            'content' => 'This is not JSON',
            'tokens' => 5,
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseCount('forms', 0);
    }

    public function test_handles_provider_exceptions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(AIProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(AIProviderException::unavailable('Server Error'));
        });

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'AI provider is currently unavailable. Please try again later.');

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
        ]);
    }

    public function test_handles_rate_limiting(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(AIProviderInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(AIProviderException::rateLimited('Too many requests'));
        });

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_strips_markdown_from_json(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mockProvider(fn () => [
            'content' => "```json\n".json_encode([
                'title' => 'MD Form',
                'fields' => [['label' => 'Name', 'type' => 'text']],
            ])."\n```",
            'tokens' => 10,
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'MD Form');
    }

    public function test_validates_generate_request(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['prompt']]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertUnauthorized();
    }

    public function test_normalizes_unsupported_field_types(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mockProvider(fn () => $this->providerPayload([
            'title' => 'Form',
            'fields' => [
                ['label' => 'Magic Field', 'type' => 'magic_unsupported_type'],
            ],
        ]));

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('form_fields', [
            'label' => 'Magic Field',
            'type' => 'text',
        ]);
    }

    public function test_handles_duplicate_field_names_by_appending_counter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mockProvider(fn () => $this->providerPayload([
            'title' => 'Form',
            'fields' => [
                ['label' => 'Email', 'type' => 'email'],
                ['label' => 'Email', 'type' => 'email'],
            ],
        ]));

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('form_fields', ['name' => 'email']);
        $this->assertDatabaseHas('form_fields', ['name' => 'email_1']);
    }

    public function test_rejects_unsupported_schema(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mockProvider(fn () => [
            'content' => json_encode(['title' => '', 'fields' => []]),
            'tokens' => 3,
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('ai_generation_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
        ]);
    }

    public function test_applies_default_options_for_select_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mockProvider(fn () => $this->providerPayload([
            'title' => 'Form',
            'fields' => [
                ['label' => 'Department', 'type' => 'select'],
            ],
        ]));

        $response = $this->withToken($token)->postJson('/api/v1/forms/generate', [
            'prompt' => 'Create a test form',
        ]);

        $response->assertCreated();

        $field = $user->forms()->first()->fields()->where('label', 'Department')->first();

        $this->assertSame(['Option 1', 'Option 2'], $field->field_options);
    }
}

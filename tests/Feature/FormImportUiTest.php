<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormImportUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_import_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forms.import'))
            ->assertOk()
            ->assertSee('Import Form');
    }

    public function test_guests_cannot_open_import_page(): void
    {
        $this->get(route('forms.import'))
            ->assertRedirect(route('login'));
    }

    public function test_forms_index_links_to_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forms.index'))
            ->assertOk()
            ->assertSee(route('forms.import'), false);
    }
}

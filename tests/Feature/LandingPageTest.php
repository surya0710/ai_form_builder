<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_authenticated_users_visiting_root_reach_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_welcome_view_is_not_used(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/welcome.blade.php'));
    }
}

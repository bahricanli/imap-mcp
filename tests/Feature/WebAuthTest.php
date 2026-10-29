<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertSee('Giriş yap');
    }

    public function test_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'dogru-sifre-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'dogru-sifre-123'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'yanlis'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_mcp_endpoint_requires_token(): void
    {
        $this->postJson('/mcp/mail', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_mcp_endpoint_returns_401_even_without_json_accept_header(): void
    {
        $this->post('/mcp/mail', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();
    }
}

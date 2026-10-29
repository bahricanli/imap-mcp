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
        $this->get('/', ['Accept' => 'text/html'])->assertRedirect('/panel');
        $this->get('/panel')->assertRedirect(route('login'));
        $this->get('/panel/login')->assertOk()->assertSee('Giriş yap');
        $this->get('/login')->assertRedirect('/panel/login');
    }

    public function test_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'dogru-sifre-123']);

        $this->post('/panel/login', ['email' => $user->email, 'password' => 'dogru-sifre-123'])
            ->assertRedirect(route('accounts.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/panel/login', ['email' => $user->email, 'password' => 'yanlis'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_mcp_endpoint_requires_token(): void
    {
        $this->postJson('/', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_root_get_for_sse_is_not_supported(): void
    {
        $this->get('/', ['Accept' => 'text/event-stream'])->assertStatus(405)->assertHeader('Allow', 'POST');
    }

    public function test_mcp_endpoint_returns_401_even_without_json_accept_header(): void
    {
        $this->post('/', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * claude.ai'nin yaptığı OAuth akışını baştan sona çalıştırır:
 * keşif → DCR → onay ekranı → onay → kod/token takası → token ile MCP çağrısı.
 * Passport anahtarları gerekir (CI'da `php artisan passport:keys`).
 */
class OAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

    public function test_full_authorization_code_flow_like_claude(): void
    {
        $user = User::factory()->create();

        // 1) Token'sız istek → 401
        $this->postJson('/', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();

        // 2) Keşif
        $this->getJson('/.well-known/oauth-protected-resource')->assertOk()->assertJsonStructure(['resource', 'authorization_servers']);
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->assertJsonPath('code_challenge_methods_supported', ['S256']);

        // 3) Dynamic Client Registration
        $clientId = $this->postJson('/oauth/register', [
            'client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT],
            'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ])->assertCreated()->json('client_id');

        // 4) Onay ekranı (girişli kullanıcı)
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = Str::random(20);

        $query = http_build_query([
            'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT,
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => $state,
            'scope' => 'mcp:use', 'resource' => url('/'),
        ]);

        // Girişsizken login'e yönlenir
        $this->get('/oauth/authorize?'.$query)->assertRedirect(route('login'));

        $page = $this->actingAs($user)->get('/oauth/authorize?'.$query)
            ->assertOk()
            ->assertSee('Claude erişim istiyor')
            ->assertSee('İzin ver')
            ->assertSee('claude.ai');

        preg_match('/name="auth_token" value="([^"]+)"/', $page->getContent(), $m);
        $this->assertNotEmpty($m[1] ?? null, 'auth_token formda olmalı');

        // 5) Onay → claude.ai'ye kodla dönüş
        $redirect = $this->actingAs($user)->post('/oauth/authorize', [
            'state' => '', 'client_id' => $clientId, 'auth_token' => $m[1],
        ])->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT, $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $params);
        $this->assertSame($state, $params['state']);
        $this->assertNotEmpty($params['code']);

        // 6) Kod → token (PKCE)
        auth()->forgetGuards();
        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier, 'code' => $params['code'],
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in'])->json();

        // 7) Token ile MCP
        $headers = ['Authorization' => 'Bearer '.$token['access_token'], 'Accept' => 'application/json, text/event-stream'];

        $this->postJson('/', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass, 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ], $headers)->assertOk();

        $this->postJson('/', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $headers)
            ->assertOk()
            ->assertSee('search-emails')
            ->assertSee('get-email');

        // 8) Refresh token çalışır
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $token['refresh_token'],
        ])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_deny_returns_to_client_with_error(): void
    {
        $user = User::factory()->create();
        $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT]])->json('client_id');

        $query = http_build_query([
            'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT,
            'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256', 'state' => 'abc', 'scope' => 'mcp:use',
        ]);

        $page = $this->actingAs($user)->get('/oauth/authorize?'.$query)->assertOk()->assertSee('Reddet');
        preg_match('/name="auth_token" value="([^"]+)"/', $page->getContent(), $m);

        $location = $this->actingAs($user)->delete('/oauth/authorize', [
            'state' => '', 'client_id' => $clientId, 'auth_token' => $m[1],
        ])->assertRedirect()->headers->get('Location');

        $this->assertStringContainsString('error=access_denied', $location);
    }
}

<?php

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\User;
use App\Services\ImapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;

class AccountsUiTest extends TestCase
{
    use RefreshDatabase;

    private function imapWorks(bool $works = true): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('disconnect');

        $imap = Mockery::mock(ImapService::class)->makePartial();
        $works
            ? $imap->shouldReceive('connect')->andReturn($client)
            : $imap->shouldReceive('connect')->andThrow(new RuntimeException('[AUTHENTICATIONFAILED] Authentication failed.'));
        $imap->shouldReceive('folders')->andReturn([['path' => 'INBOX', 'name' => 'INBOX', 'messages' => 12, 'unseen' => 3]]);
        $imap->shouldReceive('trashFolder')->andReturn(null);
        $this->app->instance(ImapService::class, $imap);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'label' => 'Kalehost', 'host' => 'mail.kalehost.net', 'port' => 993, 'encryption' => 'ssl',
            'validate_cert' => '1', 'username' => 'bahri@bahri.info', 'password' => 'imap-sifresi',
            'allow_write' => '0', 'trash_folder' => '', 'is_default' => '0',
        ];
    }

    public function test_pages_require_login(): void
    {
        foreach (['/accounts', '/accounts/create', '/connections', '/password'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_user_can_add_account_after_successful_test(): void
    {
        $user = User::factory()->create();
        $this->imapWorks();

        $this->actingAs($user)->get('/accounts/create')->assertOk()->assertSee('Posta hesabı ekle');

        $this->actingAs($user)->post('/accounts', $this->payload())
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('status');

        $account = MailAccount::sole();
        $this->assertSame('kalehost', $account->label);           // küçük harfe çevrildi
        $this->assertTrue($account->is_default);                    // ilk hesap otomatik varsayılan
        $this->assertFalse($account->allow_write);
        $this->assertSame('imap-sifresi', $account->password);
        $this->assertNotSame('imap-sifresi', DB::table('mail_accounts')->value('password'));

        $this->actingAs($user)->get('/accounts')->assertOk()->assertSee('kalehost')->assertDontSee('imap-sifresi');
    }

    public function test_account_is_not_saved_when_connection_fails(): void
    {
        $user = User::factory()->create();
        $this->imapWorks(false);

        $this->actingAs($user)->from('/accounts/create')->post('/accounts', $this->payload())
            ->assertRedirect('/accounts/create')
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'şifre hatalı'));

        $this->assertDatabaseCount('mail_accounts', 0);
    }

    public function test_validation_errors(): void
    {
        $user = User::factory()->create();
        $this->imapWorks();

        $this->actingAs($user)->post('/accounts', $this->payload(['label' => 'boşluk var', 'host' => 'kötü host', 'password' => '']))
            ->assertSessionHasErrors(['label', 'host', 'password']);
    }

    public function test_blank_password_on_update_keeps_existing_and_skips_test(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::create(['user_id' => $user->id] + $this->payload(['label' => 'kalehost']));

        $imap = Mockery::mock(ImapService::class)->makePartial();
        $imap->shouldNotReceive('connect');
        $this->app->instance(ImapService::class, $imap);

        $this->actingAs($user)->put("/accounts/{$account->id}", $this->payload([
            'label' => 'kalehost', 'password' => '', 'allow_write' => '1', 'trash_folder' => 'INBOX/Trash',
        ]))->assertRedirect(route('accounts.index'));

        $account->refresh();
        $this->assertSame('imap-sifresi', $account->password);
        $this->assertTrue($account->allow_write);
        $this->assertSame('INBOX/Trash', $account->trash_folder);
    }

    public function test_setting_default_unsets_others(): void
    {
        $user = User::factory()->create();
        $a = MailAccount::create(['user_id' => $user->id] + $this->payload(['label' => 'a', 'is_default' => true]));
        $b = MailAccount::create(['user_id' => $user->id] + $this->payload(['label' => 'b', 'is_default' => false]));
        $this->imapWorks();

        $this->actingAs($user)->put("/accounts/{$b->id}", $this->payload(['label' => 'b', 'password' => '', 'is_default' => '1']));

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_users_cannot_touch_each_others_accounts(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::create(['user_id' => $owner->id] + $this->payload(['label' => 'gizli']));
        $this->imapWorks();

        $this->actingAs($other)->get("/accounts/{$account->id}/edit")->assertNotFound();
        $this->actingAs($other)->put("/accounts/{$account->id}", $this->payload())->assertNotFound();
        $this->actingAs($other)->delete("/accounts/{$account->id}")->assertNotFound();
        $this->actingAs($other)->post("/accounts/{$account->id}/test")->assertNotFound();
        $this->actingAs($other)->get('/accounts')->assertDontSee('gizli');

        $this->assertDatabaseHas('mail_accounts', ['id' => $account->id]);
    }

    public function test_delete_moves_default_to_remaining_account(): void
    {
        $user = User::factory()->create();
        $a = MailAccount::create(['user_id' => $user->id] + $this->payload(['label' => 'a', 'is_default' => true]));
        $b = MailAccount::create(['user_id' => $user->id] + $this->payload(['label' => 'b', 'is_default' => false]));

        $this->actingAs($user)->delete("/accounts/{$a->id}")->assertRedirect(route('accounts.index'));

        $this->assertModelMissing($a);
        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_connection_test_button_reports_inbox(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::create(['user_id' => $user->id] + $this->payload(['label' => 'kalehost']));
        $this->imapWorks();

        $this->actingAs($user)->from('/accounts')->post("/accounts/{$account->id}/test")
            ->assertRedirect('/accounts')
            ->assertSessionHas('status', fn ($s) => str_contains($s, '12 mesaj'));
    }

    public function test_revoking_a_connection_revokes_access_and_refresh_tokens(): void
    {
        $user = User::factory()->create();
        $clientId = (string) \Illuminate\Support\Str::uuid();
        DB::table('oauth_clients')->insert([
            'id' => $clientId, 'name' => 'Claude', 'secret' => null, 'provider' => null,
            'redirect_uris' => '["https://claude.ai/api/mcp/auth_callback"]', 'grant_types' => '["authorization_code","refresh_token"]',
            'revoked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('oauth_access_tokens')->insert([
            'id' => 'tok1', 'user_id' => $user->id, 'client_id' => $clientId, 'name' => null, 'scopes' => '["mcp:use"]',
            'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour(),
        ]);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => 'ref1', 'access_token_id' => 'tok1', 'revoked' => false, 'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->get('/connections')->assertOk()->assertDontSee('Henüz bağlı uygulama yok')->assertSee("connections/{$clientId}", false);

        $this->actingAs($user)->delete("/connections/{$clientId}")->assertRedirect(route('connections.index'));

        $this->assertTrue((bool) DB::table('oauth_access_tokens')->where('id', 'tok1')->value('revoked'));
        $this->assertTrue((bool) DB::table('oauth_refresh_tokens')->where('id', 'ref1')->value('revoked'));
        $this->actingAs($user)->get('/connections')->assertSee('Henüz bağlı uygulama yok');
    }

    public function test_password_change(): void
    {
        $user = User::factory()->create(['password' => 'eski-sifre-12345']);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'yanlis', 'password' => 'yeni-sifre-12345', 'password_confirmation' => 'yeni-sifre-12345',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/password', [
            'current_password' => 'eski-sifre-12345', 'password' => 'yeni-sifre-12345', 'password_confirmation' => 'yeni-sifre-12345',
        ])->assertRedirect(route('password.edit'));

        $this->assertTrue(Hash::check('yeni-sifre-12345', $user->fresh()->password));
    }
}

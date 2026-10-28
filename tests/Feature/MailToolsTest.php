<?php

namespace Tests\Feature;

use App\Mcp\Servers\MailServer;
use App\Mcp\Tools\GetEmailTool;
use App\Mcp\Tools\ListAccountsTool;
use App\Mcp\Tools\SearchEmailsTool;
use App\Models\MailAccount;
use App\Models\User;
use App\Services\ImapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;

class MailToolsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithAccount(): array
    {
        $user = User::factory()->create();
        $account = MailAccount::create([
            'user_id' => $user->id, 'label' => 'kalehost', 'host' => 'imap.example.com',
            'username' => 'me@example.com', 'password' => 'gizli', 'is_default' => true,
        ]);

        return [$user, $account];
    }

    public function test_password_is_encrypted_at_rest(): void
    {
        [, $account] = $this->userWithAccount();

        $raw = \DB::table('mail_accounts')->where('id', $account->id)->value('password');

        $this->assertNotSame('gizli', $raw);
        $this->assertSame('gizli', $account->fresh()->password);
    }

    public function test_list_accounts_only_shows_own_accounts_and_hides_password(): void
    {
        [$user] = $this->userWithAccount();
        $this->userWithAccount(); // başka kullanıcının hesabı

        MailServer::actingAs($user)->tool(ListAccountsTool::class)
            ->assertOk()
            ->assertSee('kalehost')
            ->assertDontSee('gizli');

        $this->assertSame(1, $user->mailAccounts()->count());
    }

    public function test_search_passes_criteria_to_imap(): void
    {
        [$user] = $this->userWithAccount();

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('disconnect')->once();

        $imap = Mockery::mock(ImapService::class)->makePartial();
        $imap->shouldReceive('connect')->once()->andReturn($client);
        $imap->shouldReceive('search')->once()
            ->withArgs(fn ($c, $criteria) => $criteria['from'] === 'fatura@' && $criteria['unseen'] === true)
            ->andReturn([['uid' => 42, 'subject' => 'Ekim faturası']]);
        $this->app->instance(ImapService::class, $imap);

        MailServer::actingAs($user)->tool(SearchEmailsTool::class, ['from' => 'fatura@', 'unseen' => true])
            ->assertOk()
            ->assertSee('Ekim faturası');
    }

    public function test_unknown_account_returns_error(): void
    {
        [$user] = $this->userWithAccount();

        MailServer::actingAs($user)->tool(SearchEmailsTool::class, ['account' => 'yok'])
            ->assertHasErrors();
    }

    public function test_get_email_requires_uid(): void
    {
        [$user] = $this->userWithAccount();

        MailServer::actingAs($user)->tool(GetEmailTool::class, [])
            ->assertHasErrors();
    }
}

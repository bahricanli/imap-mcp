<?php

namespace Tests\Feature;

use App\Mcp\Servers\MailServer;
use App\Mcp\Tools\CreateFolderTool;
use App\Mcp\Tools\DeleteEmailsTool;
use App\Mcp\Tools\DeleteFolderTool;
use App\Mcp\Tools\FlagEmailsTool;
use App\Mcp\Tools\GetEmailTool;
use App\Mcp\Tools\MoveEmailsTool;
use App\Models\MailAccount;
use App\Models\User;
use App\Services\ImapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;

class WriteToolsTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $allowWrite): User
    {
        $user = User::factory()->create();
        MailAccount::create([
            'user_id' => $user->id, 'label' => 'kalehost', 'host' => 'imap.example.com',
            'username' => 'me@example.com', 'password' => 'gizli', 'is_default' => true,
            'allow_write' => $allowWrite,
        ]);

        return $user;
    }

    /** ImapService'i bağlantı kurmadan taklit eder; gerçek account()/writableAccount() çalışır. */
    private function fakeImap(): MockInterface
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('disconnect');

        $imap = Mockery::mock(ImapService::class)->makePartial();
        $imap->shouldReceive('connect')->andReturn($client)->byDefault();
        $this->app->instance(ImapService::class, $imap);

        return $imap;
    }

    public function test_write_tools_refuse_read_only_accounts_without_connecting(): void
    {
        $user = $this->user(allowWrite: false);
        $imap = $this->fakeImap();
        $imap->shouldNotReceive('connect');
        $imap->shouldNotReceive('moveMessages');
        $imap->shouldNotReceive('deleteMessages');

        MailServer::actingAs($user)->tool(MoveEmailsTool::class, ['uids' => [1], 'to' => 'Arşiv'])->assertHasErrors();
        MailServer::actingAs($user)->tool(DeleteEmailsTool::class, ['uids' => [1]])->assertHasErrors();
        MailServer::actingAs($user)->tool(CreateFolderTool::class, ['path' => 'Yeni'])->assertHasErrors();
        MailServer::actingAs($user)->tool(DeleteFolderTool::class, ['path' => 'Eski'])->assertHasErrors();
        MailServer::actingAs($user)->tool(FlagEmailsTool::class, ['uids' => [1], 'seen' => true])->assertHasErrors();
    }

    public function test_write_tools_are_hidden_for_users_without_write_access(): void
    {
        $readOnly = $this->user(allowWrite: false);
        $writer = $this->user(allowWrite: true);

        $tool = app(MoveEmailsTool::class);

        $this->actingAs($readOnly);
        $this->assertFalse($tool->shouldRegister(new \Laravel\Mcp\Request));

        $this->actingAs($writer);
        $this->assertTrue($tool->shouldRegister(new \Laravel\Mcp\Request));
    }

    public function test_move_emails(): void
    {
        $user = $this->user(allowWrite: true);
        $imap = $this->fakeImap();
        $imap->shouldReceive('moveMessages')->once()
            ->withArgs(fn ($c, $a, $from, $uids, $to) => $from === 'INBOX' && $uids === [3, 4] && $to === 'İşlendi')
            ->andReturn(2);

        MailServer::actingAs($user)->tool(MoveEmailsTool::class, ['uids' => [3, 4], 'to' => 'İşlendi'])
            ->assertOk()
            ->assertSee('İşlendi');
    }

    public function test_move_rejects_more_than_fifty(): void
    {
        $user = $this->user(allowWrite: true);
        $this->fakeImap()->shouldNotReceive('moveMessages');

        MailServer::actingAs($user)->tool(MoveEmailsTool::class, ['uids' => range(1, 51), 'to' => 'X'])
            ->assertHasErrors();
    }

    public function test_delete_defaults_to_trash(): void
    {
        $user = $this->user(allowWrite: true);
        $imap = $this->fakeImap();
        $imap->shouldReceive('deleteMessages')->once()
            ->withArgs(fn ($c, $a, $folder, $uids, $permanent) => $permanent === false && $uids === [7])
            ->andReturn(['action' => 'moved_to_trash', 'count' => 1, 'trash' => 'Trash']);

        MailServer::actingAs($user)->tool(DeleteEmailsTool::class, ['uids' => [7]])
            ->assertOk()
            ->assertSee('moved_to_trash');
    }

    public function test_get_email_can_move_after_reading(): void
    {
        $user = $this->user(allowWrite: true);
        $imap = $this->fakeImap();
        $imap->shouldReceive('message')->once()->andReturn(['uid' => 9, 'subject' => 'Fatura', 'seen' => false]);
        $imap->shouldReceive('flagMessages')->once()->withArgs(fn ($c, $a, $f, $uids, $seen) => $uids === [9] && $seen === true)->andReturn(1);
        $imap->shouldReceive('moveMessages')->once()->withArgs(fn ($c, $a, $from, $uids, $to) => $uids === [9] && $to === 'Okundu')->andReturn(1);

        MailServer::actingAs($user)->tool(GetEmailTool::class, ['uid' => 9, 'move_to' => 'Okundu', 'mark_seen' => true])
            ->assertOk()
            ->assertSee('Okundu');
    }

    public function test_get_email_with_move_to_needs_write_access(): void
    {
        $user = $this->user(allowWrite: false);
        $imap = $this->fakeImap();
        $imap->shouldNotReceive('connect');

        MailServer::actingAs($user)->tool(GetEmailTool::class, ['uid' => 9, 'move_to' => 'Okundu'])
            ->assertHasErrors();
    }

    public function test_flag_requires_seen_or_flagged(): void
    {
        $user = $this->user(allowWrite: true);
        $this->fakeImap()->shouldNotReceive('flagMessages');

        MailServer::actingAs($user)->tool(FlagEmailsTool::class, ['uids' => [1]])->assertHasErrors();
    }
}

<?php

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return tap(User::factory()->create(['email' => 'admin@example.com']), fn ($u) => $u->forceFill(['is_admin' => true])->save());
    }

    public function test_non_admins_cannot_manage_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get('/panel/users')->assertForbidden();
        $this->actingAs($user)->post('/panel/users', ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->actingAs($user)->delete("/panel/users/{$other->id}")->assertForbidden();
        $this->actingAs($user)->post("/panel/users/{$other->id}/reset-password")->assertForbidden();
        $this->actingAs($user)->get('/panel/accounts')->assertDontSee('Kullanıcılar');

        $this->assertModelExists($other);
    }

    public function test_admin_sees_nav_link_and_users(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'ayse@example.com']);

        $this->actingAs($admin)->get('/panel/accounts')->assertSee('Kullanıcılar');
        $this->actingAs($admin)->get('/panel/users')->assertOk()->assertSee('ayse@example.com');
    }

    public function test_admin_creates_user_and_password_is_shown_once(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/panel/users', [
            'name' => 'Ayşe Yılmaz', 'email' => 'Ayse@Example.com', 'is_admin' => '0',
        ])->assertRedirect(route('users.index'));

        $generated = $response->getSession()->get('generated_password');
        $this->assertSame('ayse@example.com', $generated['email']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{5}(-[A-Za-z0-9]{5}){3}$/', $generated['password']);

        $user = User::where('email', 'ayse@example.com')->sole();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check($generated['password'], $user->password));

        // Yeni kullanıcı bu şifreyle girebilir.
        auth()->logout();
        $this->post('/login', ['email' => 'ayse@example.com', 'password' => $generated['password']])
            ->assertRedirect(route('accounts.index'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/panel/users', ['name' => 'Kopya', 'email' => 'admin@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_reset_password(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['password' => 'eski-sifre-12345']);

        $response = $this->actingAs($admin)->post("/panel/users/{$user->id}/reset-password")->assertRedirect(route('users.index'));
        $new = $response->getSession()->get('generated_password')['password'];

        $this->assertFalse(Hash::check('eski-sifre-12345', $user->fresh()->password));
        $this->assertTrue(Hash::check($new, $user->fresh()->password));
    }

    public function test_admin_cannot_delete_or_demote_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/panel/users/{$admin->id}")->assertSessionHas('error');
        $this->actingAs($admin)->post("/panel/users/{$admin->id}/toggle-admin")->assertSessionHas('error');

        $this->assertModelExists($admin);
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_toggle_admin(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post("/panel/users/{$user->id}/toggle-admin");
        $this->assertTrue($user->fresh()->is_admin);

        $this->actingAs($admin)->post("/panel/users/{$user->id}/toggle-admin");
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_deleting_user_removes_accounts_and_tokens(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        MailAccount::create([
            'user_id' => $user->id, 'label' => 'x', 'host' => 'imap.example.com', 'username' => 'u', 'password' => 'p',
        ]);
        $clientId = (string) \Illuminate\Support\Str::uuid();
        DB::table('oauth_clients')->insert([
            'id' => $clientId, 'name' => 'Claude', 'redirect_uris' => '[]', 'grant_types' => '[]', 'revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('oauth_access_tokens')->insert([
            'id' => 'tokx', 'user_id' => $user->id, 'client_id' => $clientId, 'revoked' => false,
            'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour(),
        ]);
        DB::table('oauth_refresh_tokens')->insert(['id' => 'refx', 'access_token_id' => 'tokx', 'revoked' => false]);

        $this->actingAs($admin)->delete("/panel/users/{$user->id}")->assertRedirect(route('users.index'));

        $this->assertModelMissing($user);
        $this->assertDatabaseCount('mail_accounts', 0);
        $this->assertDatabaseMissing('oauth_access_tokens', ['id' => 'tokx']);
        $this->assertDatabaseMissing('oauth_refresh_tokens', ['id' => 'refx']);
    }

    public function test_admin_does_not_see_other_users_imap_details(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        MailAccount::create([
            'user_id' => $user->id, 'label' => 'ozel-hesap', 'host' => 'gizli.example.com', 'username' => 'gizli-kullanici', 'password' => 'p',
        ]);

        $this->actingAs($admin)->get('/panel/users')->assertSee('1 posta hesabı')
            ->assertDontSee('gizli.example.com')->assertDontSee('gizli-kullanici');
        $this->actingAs($admin)->get('/panel/accounts')->assertDontSee('ozel-hesap');
    }
}

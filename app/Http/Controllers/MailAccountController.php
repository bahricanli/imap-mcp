<?php

namespace App\Http\Controllers;

use App\Http\Requests\MailAccountRequest;
use App\Models\MailAccount;
use App\Services\ImapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class MailAccountController extends Controller
{
    public function __construct(private ImapService $imap) {}

    public function index(Request $request): View
    {
        return view('accounts.index', [
            'accounts' => $request->user()->mailAccounts()->orderByDesc('is_default')->orderBy('label')->get(),
        ]);
    }

    public function create(): View
    {
        return view('accounts.form', ['account' => new MailAccount([
            'port' => 993, 'encryption' => 'ssl', 'validate_cert' => true, 'allow_write' => false,
        ])]);
    }

    public function store(MailAccountRequest $request): RedirectResponse
    {
        $account = new MailAccount($request->validated());
        $account->user_id = $request->user()->id;
        $account->is_default = $request->boolean('is_default') || ! $request->user()->mailAccounts()->exists();

        if ($error = $this->testConnection($account)) {
            return back()->withInput($request->except('password'))->with('error', $error);
        }

        $this->save($account);

        return redirect()->route('accounts.index')->with('status', "'{$account->label}' eklendi ve bağlantı doğrulandı.");
    }

    public function edit(Request $request, MailAccount $account): View
    {
        $this->authorizeOwner($request, $account);

        return view('accounts.form', ['account' => $account]);
    }

    public function update(MailAccountRequest $request, MailAccount $account): RedirectResponse
    {
        $this->authorizeOwner($request, $account);

        $data = $request->validated();
        if (blank($data['password'] ?? null)) {
            unset($data['password']); // boş bırakıldı: mevcut şifreyi koru
        }

        $account->fill($data);

        // Bağlantıyı etkileyen bir alan değiştiyse kaydetmeden önce test et.
        if ($account->isDirty(['host', 'port', 'encryption', 'validate_cert', 'username', 'password'])
            && ($error = $this->testConnection($account))) {
            return back()->withInput($request->except('password'))->with('error', $error);
        }

        $this->save($account);

        return redirect()->route('accounts.index')->with('status', "'{$account->label}' güncellendi.");
    }

    public function destroy(Request $request, MailAccount $account): RedirectResponse
    {
        $this->authorizeOwner($request, $account);

        DB::transaction(function () use ($request, $account) {
            $account->delete();

            if ($account->is_default) {
                $request->user()->mailAccounts()->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });

        return redirect()->route('accounts.index')->with('status', "'{$account->label}' silindi. Posta kutusundaki maillere dokunulmadı.");
    }

    public function test(Request $request, MailAccount $account): RedirectResponse
    {
        $this->authorizeOwner($request, $account);

        try {
            $client = $this->imap->connect($account);
            $folders = $this->imap->folders($client);
            $trash = $this->imap->trashFolder($client, $account, required: false);
            $client->disconnect();
        } catch (Throwable $e) {
            return back()->with('error', "'{$account->label}' bağlanamadı: ".$this->friendly($e));
        }

        $inbox = collect($folders)->firstWhere('path', 'INBOX');

        return back()->with('status', sprintf(
            "'%s' bağlantısı çalışıyor: %d klasör%s%s.",
            $account->label,
            count($folders),
            $inbox ? sprintf(', gelen kutusunda %d mesaj (%d okunmamış)', $inbox['messages'] ?? 0, $inbox['unseen'] ?? 0) : '',
            $trash ? ", Çöp klasörü: {$trash->full_name}" : ($account->allow_write ? ', Çöp klasörü bulunamadı — düzenle ekranından belirt' : '')
        ));
    }

    private function save(MailAccount $account): void
    {
        DB::transaction(function () use ($account) {
            if ($account->is_default) {
                MailAccount::where('user_id', $account->user_id)
                    ->when($account->exists, fn ($q) => $q->whereKeyNot($account->id))
                    ->update(['is_default' => false]);
            }

            $account->save();
        });
    }

    private function testConnection(MailAccount $account): ?string
    {
        try {
            $client = $this->imap->connect($account);
            $this->imap->folders($client);
            $client->disconnect();

            return null;
        } catch (Throwable $e) {
            return 'Kaydedilmedi, IMAP sunucusuna bağlanılamadı: '.$this->friendly($e);
        }
    }

    private function friendly(Throwable $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            str_contains($msg, 'AUTHENTICATIONFAILED'), stripos($msg, 'authentication failed') !== false,
            stripos($msg, 'invalid credentials') !== false => 'kullanıcı adı veya şifre hatalı.',
            stripos($msg, 'certificate') !== false => 'TLS sertifikası doğrulanamadı. Sunucu adını kontrol et ya da (güvendiğin bir iç sunucuysa) sertifika doğrulamasını kapat.',
            stripos($msg, 'timed out') !== false, stripos($msg, 'connection refused') !== false,
            stripos($msg, 'getaddrinfo') !== false => 'sunucuya ulaşılamadı (adres, port veya şifreleme ayarını kontrol et).',
            default => $msg,
        };
    }

    private function authorizeOwner(Request $request, MailAccount $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 404);
    }
}

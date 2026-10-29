<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

/** Kullanıcının OAuth ile bağladığı uygulamalar (Claude vb.) ve erişimlerini iptal etme. */
class ConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $connections = Token::query()
            ->where('user_id', $request->user()->id)
            ->where('revoked', false)
            ->where('expires_at', '>', now()->subDays(30)) // refresh token'ı hâlâ geçerli olabilecekler
            ->with('client')
            ->get()
            ->groupBy('client_id')
            ->map(fn ($tokens) => [
                'client'    => $tokens->first()->client,
                'connected' => $tokens->min('created_at'),
                'last_used' => $tokens->max('created_at'),
                'count'     => $tokens->count(),
            ])
            ->sortByDesc('last_used')
            ->values();

        return view('connections.index', ['connections' => $connections]);
    }

    public function destroy(Request $request, string $client): RedirectResponse
    {
        $client = Client::findOrFail($client);

        DB::transaction(function () use ($request, $client) {
            $ids = Token::where('user_id', $request->user()->id)->where('client_id', $client->getKey())->pluck('id');

            Token::whereIn('id', $ids)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $ids)->update(['revoked' => true]);
        });

        return redirect()->route('connections.index')
            ->with('status', "'{$client->name}' bağlantısı kesildi. Tekrar bağlanmak için uygulamada yeniden oturum açılması gerekir.");
    }
}

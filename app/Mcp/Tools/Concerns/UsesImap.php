<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\MailAccount;
use App\Services\ImapService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;

trait UsesImap
{
    /**
     * Hesabı çözer, IMAP'e bağlanır, $callback'i çalıştırır ve bağlantıyı her durumda kapatır.
     * $callback bir dizi döndürürse structured yanıt olur.
     *
     * @param callable(Client, MailAccount): array $callback
     */
    protected function withImap(Request $request, ImapService $imap, ?string $label, bool $write, callable $callback): Response|ResponseFactory
    {
        try {
            $account = $write
                ? $imap->writableAccount($request->user(), $label)
                : $imap->account($request->user(), $label);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $client = null;

        try {
            $client = $imap->connect($account);

            return Response::structured(['account' => $account->label] + $callback($client, $account));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return Response::error('IMAP hatası: '.$e->getMessage());
        } finally {
            rescue(fn () => $client?->disconnect(), report: false);
        }
    }

    /** Yazma araçları yalnızca en az bir hesabında yazma izni olan kullanıcılara görünür. */
    public function shouldRegister(Request $request): bool
    {
        if (! property_exists($this, 'requiresWrite') || ! $this->requiresWrite) {
            return true;
        }

        return (bool) $request->user()?->mailAccounts()->where('allow_write', true)->exists();
    }
}

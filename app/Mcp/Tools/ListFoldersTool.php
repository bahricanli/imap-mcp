<?php

namespace App\Mcp\Tools;

use App\Services\ImapService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
#[Description('Bir posta hesabındaki klasörleri (path, toplam ve okunmamış mesaj sayısı) listeler.')]
class ListFoldersTool extends Tool
{
    public function handle(Request $request, ImapService $imap): Response|ResponseFactory
    {
        try {
            $account = $imap->account($request->user(), $request->get('account'));
            $client = $imap->connect($account);
            $folders = $imap->folders($client);
            $client->disconnect();
        } catch (Throwable $e) {
            return Response::error('IMAP hatası: '.$e->getMessage());
        }

        return Response::structured(['account' => $account->label, 'folders' => $folders]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account' => $schema->string()->description('Hesap label\'ı; boşsa varsayılan hesap.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\UsesImap;
use App\Services\ImapService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('list-folders')]
#[Description('Bir posta hesabındaki klasörleri (path, toplam ve okunmamış mesaj sayısı) listeler. Diğer araçlara klasör olarak buradaki "path" verilir.')]
class ListFoldersTool extends Tool
{
    use UsesImap;

    public function handle(Request $request, ImapService $imap): Response|ResponseFactory
    {
        return $this->withImap($request, $imap, $request->get('account'), false,
            fn ($client) => ['folders' => $imap->folders($client)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account' => $schema->string()->description('Hesap label\'ı; boşsa varsayılan hesap.'),
        ];
    }
}

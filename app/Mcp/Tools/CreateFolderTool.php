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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Name('create-folder')]
#[Description('Yeni bir posta klasörü oluşturur. Alt klasör için sunucunun ayırıcısını kullan (list-folders path\'lerine bak; örn. "INBOX/Faturalar" veya "INBOX.Faturalar"). Türkçe karakter desteklenir.')]
class CreateFolderTool extends Tool
{
    use UsesImap;

    protected bool $requiresWrite = true;

    public function handle(Request $request, ImapService $imap): Response|ResponseFactory
    {
        $data = $request->validate([
            'account' => 'nullable|string',
            'path'    => 'required|string|max:200',
        ], [
            'path.required' => 'path gerekli: oluşturulacak klasörün tam yolu.',
        ]);

        return $this->withImap($request, $imap, $data['account'] ?? null, true, fn ($client, $account) => [
            'created' => $imap->createFolder($client, $account, $data['path']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account' => $schema->string()->description('Hesap label\'ı; boşsa varsayılan hesap.'),
            'path'    => $schema->string()->description('Oluşturulacak klasörün tam yolu.')->required(),
        ];
    }
}

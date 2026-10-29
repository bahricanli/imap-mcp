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

#[IsDestructive]
#[Name('delete-folder')]
#[Description('Boş bir klasörü siler. İçinde mesaj veya alt klasör olan klasörler ve sistem klasörleri (INBOX, Sent, Drafts, Trash, Junk, Archive…) silinmez. Kullanıcıdan açık onay al.')]
class DeleteFolderTool extends Tool
{
    use UsesImap;

    protected bool $requiresWrite = true;

    public function handle(Request $request, ImapService $imap): Response|ResponseFactory
    {
        $data = $request->validate([
            'account' => 'nullable|string',
            'path'    => 'required|string|max:200',
        ], [
            'path.required' => 'path gerekli: silinecek klasörün path\'i (list-folders).',
        ]);

        return $this->withImap($request, $imap, $data['account'] ?? null, true, function ($client, $account) use ($imap, $data) {
            $imap->deleteFolder($client, $account, $data['path']);

            return ['deleted' => $data['path']];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account' => $schema->string()->description('Hesap label\'ı; boşsa varsayılan hesap.'),
            'path'    => $schema->string()->description('Silinecek klasörün path\'i.')->required(),
        ];
    }
}

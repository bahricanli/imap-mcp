<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\UsesImap;
use App\Services\ImapService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive(false)]
#[IsIdempotent]
#[Description('Mesajları okundu/okunmadı ve/veya yıldızlı (flagged) olarak işaretler (uid listesi, en fazla 50).')]
class FlagEmailsTool extends Tool
{
    use UsesImap;

    protected bool $requiresWrite = true;

    public function handle(Request $request, ImapService $imap): Response|ResponseFactory
    {
        $data = $request->validate([
            'account' => 'nullable|string',
            'folder'  => 'nullable|string',
            'uids'    => 'required|array|min:1|max:'.ImapService::MAX_BATCH,
            'uids.*'  => 'integer|min:1',
            'seen'    => 'nullable|boolean|required_without:flagged',
            'flagged' => 'nullable|boolean|required_without:seen',
        ], [
            'seen.required_without'    => 'seen veya flagged değerlerinden en az birini ver.',
            'flagged.required_without' => 'seen veya flagged değerlerinden en az birini ver.',
        ]);

        $folder = $data['folder'] ?? 'INBOX';
        $seen = array_key_exists('seen', $data) && $data['seen'] !== null ? (bool) $data['seen'] : null;
        $flagged = array_key_exists('flagged', $data) && $data['flagged'] !== null ? (bool) $data['flagged'] : null;

        return $this->withImap($request, $imap, $data['account'] ?? null, true, fn ($client, $account) => [
            'updated' => $imap->flagMessages($client, $account, $folder, $data['uids'], $seen, $flagged),
            'folder'  => $folder,
            'seen'    => $seen,
            'flagged' => $flagged,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account' => $schema->string()->description('Hesap label\'ı; boşsa varsayılan hesap.'),
            'folder'  => $schema->string()->description('Mesajların klasörü. Varsayılan INBOX.'),
            'uids'    => $schema->array()->items($schema->integer())->description('İşaretlenecek mesajların uid\'leri.')->required(),
            'seen'    => $schema->boolean()->description('true: okundu, false: okunmadı.'),
            'flagged' => $schema->boolean()->description('true: yıldızla, false: yıldızı kaldır.'),
        ];
    }
}

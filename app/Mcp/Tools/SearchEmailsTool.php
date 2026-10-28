<?php

namespace App\Mcp\Tools;

use App\Services\ImapService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
#[Description('Bir klasörde e-posta arar ve en yeniden eskiye özet listesi (uid, tarih, gönderen, konu, okundu mu) döner. Gövde dönmez; içerik için get-email kullan. Hiç kriter verilmezse son 7 gün listelenir.')]
class SearchEmailsTool extends Tool
{
    public function handle(Request $request, ImapService $imap): Response
    {
        $criteria = $request->validate([
            'account' => 'nullable|string',
            'folder'  => 'nullable|string',
            'from'    => 'nullable|string|max:200',
            'to'      => 'nullable|string|max:200',
            'subject' => 'nullable|string|max:200',
            'text'    => 'nullable|string|max:200',
            'since'   => 'nullable|date',
            'before'  => 'nullable|date',
            'unseen'  => 'nullable|boolean',
            'limit'   => 'nullable|integer|min:1|max:50',
        ], [
            'since.date'  => 'since bir tarih olmalı, örn. 2026-10-01.',
            'before.date' => 'before bir tarih olmalı, örn. 2026-10-28.',
        ]);

        try {
            $account = $imap->account($request->user(), $criteria['account'] ?? null);
            $client = $imap->connect($account);
            $messages = $imap->search($client, $criteria);
            $client->disconnect();
        } catch (Throwable $e) {
            return Response::error('IMAP hatası: '.$e->getMessage());
        }

        return Response::structured([
            'account'  => $account->label,
            'folder'   => $criteria['folder'] ?? 'INBOX',
            'count'    => count($messages),
            'messages' => $messages,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account' => $schema->string()->description('Hesap label\'ı; boşsa varsayılan hesap.'),
            'folder'  => $schema->string()->description('Klasör path\'i (list-folders). Varsayılan INBOX.'),
            'from'    => $schema->string()->description('Gönderen adres/isim içinde geçen metin.'),
            'to'      => $schema->string()->description('Alıcı adres/isim içinde geçen metin.'),
            'subject' => $schema->string()->description('Konuda geçen metin.'),
            'text'    => $schema->string()->description('Başlık veya gövdede geçen metin (sunucu tarafı IMAP TEXT araması).'),
            'since'   => $schema->string()->description('Bu tarihten (dahil) sonraki mesajlar, YYYY-MM-DD.'),
            'before'  => $schema->string()->description('Bu tarihten önceki mesajlar, YYYY-MM-DD.'),
            'unseen'  => $schema->boolean()->description('Sadece okunmamışlar.'),
            'limit'   => $schema->integer()->description('En fazla kaç mesaj (1-50, varsayılan 20).'),
        ];
    }
}

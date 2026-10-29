<?php

namespace App\Mcp\Tools;

use App\Models\MailAccount;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Kullanıcının tanımlı posta hesaplarını (label, adres, varsayılan mı, yazma izni var mı) listeler. Diğer araçlarda "account" parametresine buradaki label verilir.')]
class ListAccountsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $accounts = MailAccount::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->get(['label', 'username', 'host', 'is_default', 'allow_write']);

        return Response::structured(['accounts' => $accounts->toArray()]);
    }
}

<?php

namespace Tests\Unit;

use App\Services\ImapService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webklex\PHPIMAP\ClientManager;

class ImapServiceRulesTest extends TestCase
{
    private function service(): ImapService
    {
        return new ImapService(new ClientManager);
    }

    public static function trashCases(): array
    {
        return [
            'dovecot'   => [['INBOX', 'INBOX.Sent', 'INBOX.Trash'], 'INBOX.Trash'],
            'slash'     => [['INBOX', 'Sent', 'Trash'], 'Trash'],
            'outlook'   => [['Inbox', 'Deleted Items'], 'Deleted Items'],
            'turkce'    => [['INBOX', 'Gönderilmiş', 'Çöp Kutusu'], 'Çöp Kutusu'],
            'oncelik'   => [['Deleted', 'Trash'], 'Trash'],
            'yok'       => [['INBOX', 'Arşiv'], null],
        ];
    }

    #[DataProvider('trashCases')]
    public function test_detects_trash_folder(array $paths, ?string $expected): void
    {
        $this->assertSame($expected, $this->service()->detectTrashName($paths));
    }

    public function test_protected_folders(): void
    {
        $s = $this->service();

        foreach (['INBOX', 'INBOX.Sent', 'Drafts', 'INBOX/Trash', 'Spam', 'Çöp', '[Gmail]/All Mail', 'Arşiv'] as $p) {
            $this->assertTrue($s->isProtectedFolder($p), "$p korunmalı");
        }

        foreach (['Faturalar', 'INBOX/Müşteriler', 'İşlendi', 'Projeler.2026'] as $p) {
            $this->assertFalse($s->isProtectedFolder($p), "$p silinebilmeli");
        }
    }

    public function test_normalize_uids(): void
    {
        $this->assertSame([3, 5], $this->service()->normalizeUids(['3', 5, 5, 0, -1]));
    }

    public function test_normalize_uids_rejects_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service()->normalizeUids([0, -2]);
    }

    public function test_normalize_uids_rejects_large_batches(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service()->normalizeUids(range(1, 51));
    }
}

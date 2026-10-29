<?php

namespace App\Services;

use App\Models\MailAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

/**
 * IMAP katmanı.
 *
 * Okuma metotları mesajları okundu işaretlemez (FT_PEEK + leaveUnread).
 * Yazma metotları yalnızca allow_write açık hesaplarda çağrılır (writableAccount),
 * her işlem loglanır ve aşağıdaki güvenlik kuralları uygulanır:
 *  - Silme varsayılan olarak Çöp'e taşır; kalıcı silme sadece Çöp klasöründen yapılır.
 *  - Sistem klasörleri (INBOX, Sent, Drafts, Trash, Junk…) ve dolu/alt klasörlü klasörler silinmez.
 *  - Tek çağrıda en fazla MAX_BATCH mesaj.
 *
 * Klasör yolları dışarıya her zaman UTF-8 (çözülmüş) verilir ve UTF-8 alınır;
 * sunucuya giden ham yol (UTF7-IMAP) Folder::$path ile kullanılır.
 */
class ImapService
{
    public const MAX_BATCH = 50;

    private const BODY_LIMIT = 20_000;

    /** Silinmesine izin verilmeyen klasörlerin (son segment, küçük harf) adları. */
    private const PROTECTED_NAMES = [
        'inbox', 'sent', 'sent items', 'sent messages', 'gönderilmiş', 'gönderilenler', 'gönderilmiş öğeler',
        'drafts', 'taslaklar', 'trash', 'deleted', 'deleted items', 'deleted messages', 'çöp', 'çöp kutusu',
        'silinmiş öğeler', 'junk', 'spam', 'junk e-mail', 'istenmeyen', 'gereksiz', 'archive', 'arşiv',
        'all mail', 'tüm postalar', 'starred', 'important',
    ];

    /** Çöp klasörünü otomatik bulmak için denenecek adlar (öncelik sırasıyla). */
    private const TRASH_NAMES = ['trash', 'deleted items', 'deleted messages', 'deleted', 'çöp', 'çöp kutusu', 'silinmiş öğeler', 'bin'];

    public function __construct(private ClientManager $manager) {}

    // ---------------------------------------------------------------- hesaplar

    public function account(User $user, ?string $label = null): MailAccount
    {
        $query = MailAccount::where('user_id', $user->id);

        $account = $label
            ? $query->where('label', $label)->first()
            : $query->orderByDesc('is_default')->orderBy('id')->first();

        if (! $account) {
            throw new RuntimeException($label
                ? "'{$label}' adında bir posta hesabın yok. list-accounts ile hesapları gör."
                : 'Tanımlı posta hesabın yok.');
        }

        return $account;
    }

    public function writableAccount(User $user, ?string $label = null): MailAccount
    {
        $account = $this->account($user, $label);

        if (! $account->allow_write) {
            throw new RuntimeException(
                "'{$account->label}' hesabında yazma izni kapalı. Sunucuda "
                ."`php artisan mail:set-write {$user->email} {$account->label} on` ile açılabilir."
            );
        }

        return $account;
    }

    public function connect(MailAccount $account): Client
    {
        $client = $this->manager->make([
            'host'          => $account->host,
            'port'          => $account->port,
            'encryption'    => $account->encryption === 'none' ? false : $account->encryption,
            'validate_cert' => $account->validate_cert,
            'username'      => $account->username,
            'password'      => $account->password,
            'protocol'      => 'imap',
            'timeout'       => 20,
        ]);

        $client->connect();

        return $client;
    }

    // ---------------------------------------------------------------- okuma

    /** @return array<int, array{path:string,name:string,messages:?int,unseen:?int}> */
    public function folders(Client $client): array
    {
        $out = [];

        foreach ($client->getFolders(false) as $folder) {
            // IMAP STATUS: klasörü seçmeden sayıları verir.
            $status = $folder->no_select ? [] : rescue(fn () => $folder->status(), [], report: false);

            $out[] = [
                'path'     => $folder->full_name, // UTF-8; diğer araçlara bu verilir
                'name'     => $folder->name,
                'messages' => $status['messages'] ?? null,
                'unseen'   => $status['unseen'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array{folder?:string,from?:string,to?:string,subject?:string,text?:string,
     *              since?:string,before?:string,unseen?:bool,limit?:int} $criteria
     */
    public function search(Client $client, array $criteria): array
    {
        $folder = $this->folder($client, $criteria['folder'] ?? 'INBOX');

        $query = $folder->query()
            ->leaveUnread()
            ->setFetchBody(false)
            ->setFetchOrder('desc')
            ->softFail(true);

        $hasCriteria = false;

        foreach (['from', 'to', 'subject', 'text'] as $key) {
            if (! empty($criteria[$key])) {
                $query->where(strtoupper($key), $criteria[$key]);
                $hasCriteria = true;
            }
        }

        if (! empty($criteria['since'])) {
            $query->since(Carbon::parse($criteria['since']));
            $hasCriteria = true;
        }

        if (! empty($criteria['before'])) {
            $query->before(Carbon::parse($criteria['before']));
            $hasCriteria = true;
        }

        if (! empty($criteria['unseen'])) {
            $query->unseen();
            $hasCriteria = true;
        }

        // Kriter yoksa tüm kutuyu taramak yerine son 7 güne bak.
        if (! $hasCriteria) {
            $query->since(now()->subDays(7));
        }

        $messages = $query->limit(min((int) ($criteria['limit'] ?? 20), self::MAX_BATCH))->get();

        // En yeniden eskiye (sunucu sırası her zaman güvenilir değil).
        return $messages->map(fn (Message $m) => $this->summary($m))
            ->sortByDesc('uid')->values()->all();
    }

    public function message(Client $client, string $folderPath, int $uid): array
    {
        $message = $this->folder($client, $folderPath)->query()->leaveUnread()->getMessageByUid($uid);

        $body = $message->hasTextBody()
            ? $message->getTextBody()
            : strip_tags(Str::replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $message->getHTMLBody() ?? ''));

        $body = trim(preg_replace("/\n{3,}/", "\n\n", $body));

        return $this->summary($message) + [
            'cc'          => $this->addresses($message->getCc()),
            'body'        => Str::limit($body, self::BODY_LIMIT, "\n…[kesildi]"),
            'attachments' => $message->getAttachments()->map(fn ($a) => [
                'name' => $a->getName(),
                'mime' => $a->getMimeType(),
                'size' => $a->getSize(),
            ])->values()->all(),
        ];
    }

    // ---------------------------------------------------------------- yazma

    public function createFolder(Client $client, MailAccount $account, string $path): string
    {
        $path = trim($path);

        if ($path === '' || mb_strlen($path) > 200) {
            throw new RuntimeException('Klasör adı boş olamaz ve 200 karakteri geçemez.');
        }

        if ($client->getFolderByPath($path, soft_fail: true)) {
            throw new RuntimeException("'{$path}' klasörü zaten var.");
        }

        $folder = $client->createFolder($path, expunge: false);
        $this->audit($account, 'create-folder', ['folder' => $path]);

        return $folder?->full_name ?? $path;
    }

    public function deleteFolder(Client $client, MailAccount $account, string $path): void
    {
        $folder = $this->folder($client, $path);

        if ($this->isProtectedFolder($folder->full_name) || $this->sameFolder($folder, $this->trashFolder($client, $account, required: false))) {
            throw new RuntimeException("'{$folder->full_name}' bir sistem klasörü; silinemez.");
        }

        if ($folder->has_children || $folder->hasChildren()) {
            throw new RuntimeException("'{$folder->full_name}' alt klasörler içeriyor; önce onları sil.");
        }

        $count = (int) ($folder->status()['messages'] ?? 0);
        if ($count > 0) {
            throw new RuntimeException("'{$folder->full_name}' içinde {$count} mesaj var. Sadece boş klasörler silinir; önce mesajları taşı veya sil.");
        }

        $folder->delete(expunge: false);
        $this->audit($account, 'delete-folder', ['folder' => $folder->full_name]);
    }

    /** @param int[] $uids */
    public function moveMessages(Client $client, MailAccount $account, string $fromPath, array $uids, string $toPath): int
    {
        $uids = $this->normalizeUids($uids);
        $from = $this->folder($client, $fromPath);
        $to = $this->folder($client, $toPath);

        if ($this->sameFolder($from, $to)) {
            throw new RuntimeException('Kaynak ve hedef klasör aynı.');
        }

        $client->openFolder($from->path, force_select: true);
        $client->getConnection()->moveManyMessages($uids, $to->path, IMAP::ST_UID)->validatedData();

        $this->audit($account, 'move', ['from' => $from->full_name, 'to' => $to->full_name, 'uids' => $uids]);

        return count($uids);
    }

    /**
     * Varsayılan: Çöp'e taşır. permanent=true: sadece Çöp klasöründeki mesajları kalıcı siler.
     *
     * @param int[] $uids
     * @return array{action:string,count:int,trash?:string}
     */
    public function deleteMessages(Client $client, MailAccount $account, string $fromPath, array $uids, bool $permanent = false): array
    {
        $uids = $this->normalizeUids($uids);
        $from = $this->folder($client, $fromPath);
        $trash = $this->trashFolder($client, $account, required: true);

        if (! $permanent) {
            if ($this->sameFolder($from, $trash)) {
                throw new RuntimeException('Mesajlar zaten Çöp klasöründe. Kalıcı silmek için permanent=true kullan.');
            }

            $this->moveMessages($client, $account, $from->full_name, $uids, $trash->full_name);

            return ['action' => 'moved_to_trash', 'count' => count($uids), 'trash' => $trash->full_name];
        }

        if (! $this->sameFolder($from, $trash)) {
            throw new RuntimeException(
                "Kalıcı silme sadece Çöp klasöründen ({$trash->full_name}) yapılabilir. Önce permanent=false ile Çöp'e taşı."
            );
        }

        $client->openFolder($from->path, force_select: true);
        $connection = $client->getConnection();
        foreach ($uids as $uid) {
            $connection->store(['\Deleted'], $uid, $uid, '+', true, IMAP::ST_UID)->validatedData();
        }
        $connection->expunge()->validatedData();

        $this->audit($account, 'delete-permanent', ['folder' => $from->full_name, 'uids' => $uids]);

        return ['action' => 'deleted_permanently', 'count' => count($uids)];
    }

    /** @param int[] $uids */
    public function flagMessages(Client $client, MailAccount $account, string $path, array $uids, ?bool $seen, ?bool $flagged): int
    {
        $uids = $this->normalizeUids($uids);
        $folder = $this->folder($client, $path);

        $client->openFolder($folder->path, force_select: true);
        $connection = $client->getConnection();

        foreach ($uids as $uid) {
            if ($seen !== null) {
                $connection->store(['\Seen'], $uid, $uid, $seen ? '+' : '-', true, IMAP::ST_UID)->validatedData();
            }
            if ($flagged !== null) {
                $connection->store(['\Flagged'], $uid, $uid, $flagged ? '+' : '-', true, IMAP::ST_UID)->validatedData();
            }
        }

        $this->audit($account, 'flag', ['folder' => $folder->full_name, 'uids' => $uids, 'seen' => $seen, 'flagged' => $flagged]);

        return count($uids);
    }

    // ---------------------------------------------------------------- yardımcılar

    public function folder(Client $client, string $path): Folder
    {
        return $client->getFolderByPath($path, soft_fail: true)
            ?? throw new RuntimeException("Klasör bulunamadı: {$path}. list-folders ile mevcut klasörleri gör.");
    }

    public function trashFolder(Client $client, MailAccount $account, bool $required = true): ?Folder
    {
        if ($account->trash_folder) {
            return $required ? $this->folder($client, $account->trash_folder) : $client->getFolderByPath($account->trash_folder, soft_fail: true);
        }

        $name = $this->detectTrashName(
            $client->getFolders(false)->map(fn (Folder $f) => $f->full_name)->all()
        );

        if ($name !== null) {
            return $client->getFolderByPath($name, soft_fail: true);
        }

        if ($required) {
            throw new RuntimeException(
                "Çöp klasörü bulunamadı. `php artisan mail:set-write <email> {$account->label} on --trash=\"Klasör/Yolu\"` ile belirt."
            );
        }

        return null;
    }

    /** @param string[] $paths */
    public function detectTrashName(array $paths): ?string
    {
        $byLeaf = [];
        foreach ($paths as $path) {
            $byLeaf[$this->leaf($path)] ??= $path;
        }

        foreach (self::TRASH_NAMES as $candidate) {
            if (isset($byLeaf[$candidate])) {
                return $byLeaf[$candidate];
            }
        }

        return null;
    }

    public function isProtectedFolder(string $path): bool
    {
        return in_array($this->leaf($path), self::PROTECTED_NAMES, true);
    }

    /** @param array<int, int|string> $uids @return int[] */
    public function normalizeUids(array $uids): array
    {
        $uids = array_values(array_unique(array_map('intval', $uids)));
        $uids = array_values(array_filter($uids, fn ($u) => $u > 0));

        if ($uids === []) {
            throw new RuntimeException('En az bir geçerli uid gerekli.');
        }

        if (count($uids) > self::MAX_BATCH) {
            throw new RuntimeException('Tek seferde en fazla '.self::MAX_BATCH.' mesaj işlenebilir.');
        }

        return $uids;
    }

    private function leaf(string $path): string
    {
        $parts = preg_split('~[/.]~', $path);

        return mb_strtolower(trim((string) end($parts)));
    }

    private function sameFolder(?Folder $a, ?Folder $b): bool
    {
        return $a !== null && $b !== null && $a->path === $b->path;
    }

    private function audit(MailAccount $account, string $action, array $context): void
    {
        Log::info("mail-mcp: {$action}", [
            'user_id' => $account->user_id,
            'account' => $account->label,
        ] + $context);
    }

    private function summary(Message $m): array
    {
        $date = rescue(fn () => $m->getDate()->toDate(), null, report: false);

        return [
            'uid'     => $m->getUid(),
            'date'    => $date?->toIso8601String(),
            'from'    => $this->addresses($m->getFrom()),
            'to'      => $this->addresses($m->getTo()),
            'subject' => $this->decodeHeader((string) $m->getSubject()),
            'seen'    => $m->getFlags()->has('seen'),
        ];
    }

    /**
     * RFC 2047 kodlu başlıkları (=?UTF-8?B?...?= / =?UTF-8?Q?...?=) çözer.
     * Kodlama yoksa veya çözülemezse metni olduğu gibi döner.
     */
    public function decodeHeader(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return $value;
        }

        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        if ($decoded === false || $decoded === '') {
            $decoded = mb_decode_mimeheader($value);
        }

        return trim(preg_replace('/\s+/u', ' ', $decoded));
    }

    private function addresses($attribute): array
    {
        if (! $attribute) {
            return [];
        }

        return collect($attribute->all())
            ->map(fn ($a) => trim(($a->personal ? $this->decodeHeader((string) $a->personal).' ' : '')."<{$a->mail}>"))
            ->values()
            ->all();
    }
}

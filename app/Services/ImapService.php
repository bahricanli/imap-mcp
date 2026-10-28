<?php

namespace App\Services;

use App\Models\MailAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Salt-okunur IMAP katmanı. Hiçbir metot mesaj silmez, taşımaz ya da
 * \Seen bayrağını değiştirmez (FT_PEEK + leaveUnread).
 */
class ImapService
{
    private const BODY_LIMIT = 20_000; // karakter; modele dev HTML yığını gitmesin

    public function __construct(private ClientManager $manager) {}

    /** Kullanıcının hesabını label ile (yoksa varsayılanı) bulur. */
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

    /** @return array<int, array{path:string,name:string,messages:?int,unseen:?int}> */
    public function folders(Client $client): array
    {
        $out = [];

        foreach ($client->getFolders(false) as $folder) {
            // IMAP STATUS: klasörü seçmeden sayıları verir (examine'deki 'unseen' sayı değil).
            $status = rescue(fn () => $folder->status(), [], report: false);

            $out[] = [
                'path'     => $folder->path,
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
        $folder = $client->getFolderByPath($criteria['folder'] ?? 'INBOX')
            ?? throw new RuntimeException("Klasör bulunamadı: ".($criteria['folder'] ?? 'INBOX'));

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

        $messages = $query->limit(min((int) ($criteria['limit'] ?? 20), 50))->get();

        return $messages->map(fn (Message $m) => $this->summary($m))->values()->all();
    }

    public function message(Client $client, string $folderPath, int $uid): array
    {
        $folder = $client->getFolderByPath($folderPath)
            ?? throw new RuntimeException("Klasör bulunamadı: {$folderPath}");

        $message = $folder->query()->leaveUnread()->getMessageByUid($uid);

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

    private function summary(Message $m): array
    {
        $date = rescue(fn () => $m->getDate()->toDate(), null, report: false);

        return [
            'uid'     => $m->getUid(),
            'date'    => $date?->toIso8601String(),
            'from'    => $this->addresses($m->getFrom()),
            'to'      => $this->addresses($m->getTo()),
            'subject' => (string) $m->getSubject(),
            'seen'    => $m->getFlags()->has('seen'),
        ];
    }

    private function addresses($attribute): array
    {
        if (! $attribute) {
            return [];
        }

        return collect($attribute->all())
            ->map(fn ($a) => trim(($a->personal ? "{$a->personal} " : '')."<{$a->mail}>"))
            ->values()
            ->all();
    }
}

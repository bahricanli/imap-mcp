<?php

namespace App\Console\Commands;

use App\Models\MailAccount;
use App\Models\User;
use App\Services\ImapService;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * php artisan mail:add-account you@example.com
 * Panel yazana kadar hesap eklemenin en kısa yolu.
 */
class AddMailAccount extends Command
{
    protected $signature = 'mail:add-account {email : Hesabın ekleneceği Laravel kullanıcısının e-postası}';

    protected $description = 'Bir kullanıcıya IMAP hesabı ekler ve bağlantıyı test eder';

    public function handle(ImapService $imap): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();

        $account = new MailAccount([
            'user_id'       => $user->id,
            'label'         => text('Label', placeholder: 'kalehost', required: true),
            'host'          => text('IMAP host', placeholder: 'mail.example.com', required: true),
            'port'          => (int) text('Port', default: '993'),
            'encryption'    => select('Şifreleme', ['ssl', 'tls', 'starttls', 'none'], default: 'ssl'),
            'validate_cert' => confirm('Sertifika doğrulansın mı?', default: true),
            'allow_write'   => confirm('Yapay zeka bu hesapta klasör oluşturma, taşıma ve silme yapabilsin mi?', default: false),
            'username'      => text('Kullanıcı adı', required: true),
            'password'      => password('Şifre / uygulama şifresi', required: true),
            'is_default'    => ! MailAccount::where('user_id', $user->id)->exists(),
        ]);

        try {
            $client = $imap->connect($account);
            $count = count($imap->folders($client));
            $client->disconnect();
        } catch (Throwable $e) {
            $this->error('Bağlanılamadı: '.$e->getMessage());

            return self::FAILURE;
        }

        $account->save();
        $this->info("Kaydedildi ({$count} klasör görüldü).");

        return self::SUCCESS;
    }
}

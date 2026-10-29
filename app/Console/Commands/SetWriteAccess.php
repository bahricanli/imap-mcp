<?php

namespace App\Console\Commands;

use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * php artisan mail:set-write sen@ornek.com kalehost on [--trash="INBOX/Trash"]
 */
class SetWriteAccess extends Command
{
    protected $signature = 'mail:set-write
        {email : Laravel kullanıcısının e-postası}
        {label : Posta hesabının label\'ı}
        {state : on | off}
        {--trash= : Çöp klasörünün yolu (boşsa otomatik bulunur)}';

    protected $description = 'Bir posta hesabında yazma izinlerini (klasör oluşturma, taşıma, silme) açar veya kapatır';

    public function handle(): int
    {
        $state = strtolower($this->argument('state'));
        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('state "on" veya "off" olmalı.');

            return self::INVALID;
        }

        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $account = MailAccount::where('user_id', $user->id)->where('label', $this->argument('label'))->firstOrFail();

        $account->allow_write = $state === 'on';
        if ($this->option('trash') !== null) {
            $account->trash_folder = $this->option('trash') ?: null;
        }
        $account->save();

        $this->info(sprintf(
            "'%s' hesabında yazma izni %s.%s",
            $account->label,
            $account->allow_write ? 'AÇIK' : 'KAPALI',
            $account->trash_folder ? " Çöp klasörü: {$account->trash_folder}" : ''
        ));

        return self::SUCCESS;
    }
}

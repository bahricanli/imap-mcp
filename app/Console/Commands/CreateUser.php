<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * php artisan mail:create-user
 * Açık kayıt olmadığı için kullanıcılar buradan eklenir.
 */
class CreateUser extends Command
{
    protected $signature = 'mail:create-user {--admin : Kullanıcıyı yönetici yap (ilk kullanıcı her zaman yöneticidir)}';

    protected $description = 'MCP sunucusuna giriş yapabilecek bir kullanıcı oluşturur';

    public function handle(): int
    {
        $isFirst = ! User::exists();

        $user = User::create([
            'name'     => text('Ad', required: true),
            'email'    => text('E-posta', required: true, validate: fn ($v) => match (true) {
                ! filter_var($v, FILTER_VALIDATE_EMAIL) => 'Geçerli bir e-posta gir.',
                User::where('email', $v)->exists()      => 'Bu e-posta zaten kayıtlı.',
                default                                  => null,
            }),
            'password' => password('Şifre', required: true, validate: fn ($v) => strlen($v) < 12 ? 'En az 12 karakter.' : null),
        ]);

        if ($isFirst || $this->option('admin')) {
            $user->forceFill(['is_admin' => true])->save();
        }

        $this->info("Kullanıcı oluşturuldu: {$user->email}".($user->is_admin ? ' (yönetici)' : ''));

        return self::SUCCESS;
    }
}

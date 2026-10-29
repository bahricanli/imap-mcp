<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAccount extends Model
{
    protected $fillable = [
        'user_id', 'label', 'host', 'port', 'encryption',
        'validate_cert', 'allow_write', 'trash_folder', 'username', 'password', 'is_default',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password'      => 'encrypted',   // APP_KEY ile şifreli saklanır
            'validate_cert' => 'boolean',
            'allow_write'   => 'boolean',
            'is_default'    => 'boolean',
            'port'          => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'label'         => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('mail_accounts')->where('user_id', $this->user()->id)->ignore($account?->id),
            ],
            'host'          => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port'          => ['required', 'integer', 'between:1,65535'],
            'encryption'    => ['required', Rule::in(['ssl', 'tls', 'starttls', 'none'])],
            'username'      => ['required', 'string', 'max:255'],
            // Düzenlemede boş bırakılırsa mevcut şifre korunur.
            'password'      => [$account ? 'nullable' : 'required', 'string', 'max:500'],
            'trash_folder'  => ['nullable', 'string', 'max:200'],
            'validate_cert' => ['boolean'],
            'allow_write'   => ['boolean'],
            'is_default'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.regex'       => 'Label küçük harf, rakam, nokta, tire veya alt çizgiden oluşmalı (örn. kalehost).',
            'label.unique'      => 'Bu label ile başka bir hesabın var.',
            'host.regex'        => 'Geçerli bir sunucu adı gir (örn. mail.kalehost.net).',
            'password.required' => 'IMAP şifresi gerekli.',
        ];
    }

    public function attributes(): array
    {
        return [
            'label' => 'label', 'host' => 'IMAP sunucusu', 'port' => 'port', 'username' => 'kullanıcı adı',
            'password' => 'şifre', 'trash_folder' => 'Çöp klasörü',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label'         => strtolower(trim((string) $this->input('label'))),
            'host'          => trim((string) $this->input('host')),
            'username'      => trim((string) $this->input('username')),
            'trash_folder'  => trim((string) $this->input('trash_folder')) ?: null,
            'validate_cert' => $this->boolean('validate_cert'),
            'allow_write'   => $this->boolean('allow_write'),
            'is_default'    => $this->boolean('is_default'),
        ]);
    }
}

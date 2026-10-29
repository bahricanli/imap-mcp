<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            // Varsayılan kapalı: taşıma/silme/klasör işlemleri hesap bazında açıkça izin ister.
            $table->boolean('allow_write')->default(false)->after('validate_cert');
            // Boşsa Trash / Çöp / Deleted Items gibi adlardan otomatik bulunur.
            $table->string('trash_folder')->nullable()->after('allow_write');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->dropColumn(['allow_write', 'trash_folder']);
        });
    }
};

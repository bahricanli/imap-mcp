<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');                       // "kalehost", "kisisel" ...
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption')->default('ssl');  // ssl | tls | starttls | none
            $table->boolean('validate_cert')->default(true);
            $table->string('username');
            $table->text('password');                      // encrypted cast
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};

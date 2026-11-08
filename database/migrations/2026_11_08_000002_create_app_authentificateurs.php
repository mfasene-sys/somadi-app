<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application d'authentification (codes TOTP) et codes de secours.
 * - app_authentificateurs : une clé par compte, chiffrée avec la clé de l'application (APP_KEY) ;
 *   dernier_pas empêche de réutiliser un code déjà accepté.
 * - app_codes_secours : codes à usage unique, stockés sous forme d'empreinte HMAC (jamais en clair).
 * L'ancienne application ignore ces deux tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_authentificateurs')) {
            Schema::create('app_authentificateurs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('user_id')->unique('app_authentificateurs_user');
                $table->text('cle_chiffree');
                $table->dateTime('active_le');
                $table->unsignedBigInteger('dernier_pas')->nullable();
                $table->dateTime('utilise_le')->nullable();
            });
        }

        if (! Schema::hasTable('app_codes_secours')) {
            Schema::create('app_codes_secours', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('user_id');
                $table->char('code_hash', 64);
                $table->dateTime('cree_le');
                $table->dateTime('utilise_le')->nullable();

                $table->index('user_id', 'app_codes_secours_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_codes_secours');
        Schema::dropIfExists('app_authentificateurs');
    }
};

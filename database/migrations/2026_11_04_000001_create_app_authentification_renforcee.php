<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentification renforcée.
 * - app_codes_connexion : codes PIN à usage unique envoyés par e-mail après le mot de passe (second facteur).
 * - app_empreintes : clés d'accès WebAuthn (empreinte digitale du téléphone). Seule la clé PUBLIQUE est conservée ;
 *   l'empreinte elle-même ne quitte jamais l'appareil.
 * L'ancienne application ignore ces deux tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_codes_connexion')) {
            Schema::create('app_codes_connexion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('user_id');
                $table->char('code_hash', 64); // HMAC-SHA256 du code : le code en clair n'est jamais stocké
                $table->dateTime('cree_le');
                $table->dateTime('expire_le');
                $table->unsignedTinyInteger('tentatives')->default(0);
                $table->dateTime('utilise_le')->nullable();
                $table->string('ip', 45)->nullable();

                $table->index(['user_id', 'cree_le'], 'app_codes_connexion_user');
            });
        }

        if (! Schema::hasTable('app_empreintes')) {
            Schema::create('app_empreintes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('user_id');
                $table->string('credential_id', 255); // identifiant de la clé (base64url)
                $table->text('cle_publique'); // clé publique PEM
                $table->unsignedBigInteger('compteur')->default(0);
                $table->string('appareil', 100)->nullable();
                $table->dateTime('cree_le');
                $table->dateTime('utilise_le')->nullable();

                $table->unique('credential_id', 'app_empreintes_credential');
                $table->index('user_id', 'app_empreintes_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_empreintes');
        Schema::dropIfExists('app_codes_connexion');
    }
};

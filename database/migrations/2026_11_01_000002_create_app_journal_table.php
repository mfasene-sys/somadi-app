<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EC19 : journal métier unique. L'auteur est identifié par son id (pérenne) et son nom au moment de l'action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_journal', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('cree_le')->useCurrent();
            $table->integer('user_id')->nullable();
            $table->string('user_nom', 150)->nullable();
            $table->string('module', 10)->nullable();
            $table->string('action', 60);
            $table->string('objet_type', 60)->nullable();
            $table->string('objet_id', 64)->nullable();
            $table->longText('avant')->nullable();
            $table->longText('apres')->nullable();
            $table->text('motif')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->index(['objet_type', 'objet_id'], 'app_journal_objet');
            $table->index('user_id', 'app_journal_user');
            $table->index('cree_le', 'app_journal_date');
            $table->index(['module', 'action'], 'app_journal_module_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_journal');
    }
};

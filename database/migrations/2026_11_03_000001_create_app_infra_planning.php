<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EC01 : les tables modInfra_planning / modInfra_planning_schema attendues par l'ancien code (V1.18)
     * n'existent pas dans la base fournie. Le planning des emplacements est donc tenu dans une table app_,
     * dérivée des accès décrits dans le code (RG-IFP-002 à 012). L'ancienne application l'ignore.
     */
    public function up(): void
    {
        if (Schema::hasTable('app_infra_planning')) {
            return;
        }
        Schema::create('app_infra_planning', function (Blueprint $table) {
            $table->id();
            $table->integer('id_batiment');
            $table->string('nature', 20); // reservation, nettoyage, vide_sanitaire, maintenance
            $table->string('statut', 12)->default('prevu'); // prevu, en_cours, termine, annule
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('quantite')->default(0);
            $table->string('libelle', 150);
            $table->text('commentaire')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->integer('cree_par')->nullable();
            $table->dateTime('cree_le');
            $table->dateTime('demarre_le')->nullable();
            $table->dateTime('termine_le')->nullable();
            $table->string('cloture_mode', 20)->nullable(); // normale, administrative, reservation
            $table->text('motif')->nullable();
            $table->index(['id_batiment', 'statut', 'date_debut'], 'app_infra_planning_lieu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_infra_planning');
    }
};

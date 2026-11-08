<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changement d'identité : quand un administrateur agit sous le compte d'un autre utilisateur,
 * le journal garde le compte utilisé (user_id) ET l'administrateur réel (agi_par_id).
 * Seule la table app_journal (propre à la nouvelle application) est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('app_journal', 'agi_par_id')) {
            Schema::table('app_journal', function (Blueprint $table) {
                $table->integer('agi_par_id')->nullable()->after('user_nom');
                $table->string('agi_par_nom', 150)->nullable()->after('agi_par_id');
                $table->index('agi_par_id', 'app_journal_agi_par');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_journal', 'agi_par_id')) {
            Schema::table('app_journal', function (Blueprint $table) {
                $table->dropIndex('app_journal_agi_par');
                $table->dropColumn(['agi_par_id', 'agi_par_nom']);
            });
        }
    }
};

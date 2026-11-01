<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anti double envoi des formulaires de création (INT11).
        Schema::create('app_requetes', function (Blueprint $table) {
            $table->char('cle', 36)->primary();
            $table->integer('user_id')->nullable();
            $table->dateTime('cree_le');
            $table->index('cree_le', 'app_requetes_date');
        });

        // EC10 : exclusion paramétrable des analyses de rentabilité, à la place des identifiants codés en dur.
        Schema::create('app_lots_options', function (Blueprint $table) {
            $table->integer('id_lot')->primary();
            $table->boolean('exclu_analyses')->default(false);
            $table->string('motif_exclusion', 255)->nullable();
            $table->timestamps();
        });

        // Les lots 27 et 9, exclus en dur dans l'ancienne fiche, sont exclus au départ (en attente de leur raison).
        foreach ([27, 9] as $id) {
            if (DB::table('modLotVol_lots_volailles')->where('id', $id)->exists()) {
                DB::table('app_lots_options')->insert([
                    'id_lot' => $id, 'exclu_analyses' => true,
                    'motif_exclusion' => 'Exclu dans l’ancienne fiche de rentabilité (raison à confirmer)',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_lots_options');
        Schema::dropIfExists('app_requetes');
    }
};

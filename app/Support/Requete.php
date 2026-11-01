<?php

namespace App\Support;

use App\Exceptions\RegleMetier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Anti double envoi (INT11) : chaque formulaire de création porte une clé unique ;
 * la clé est consommée dans la même transaction que l'écriture.
 */
class Requete
{
    public const CHAMP = '_cle';

    public static function consommer(Request $request): void
    {
        $cle = (string) $request->input(self::CHAMP);
        if (! preg_match('/^[a-f0-9-]{36}$/', $cle)) {
            throw new RegleMetier('Formulaire expiré : rechargez la page.');
        }
        try {
            DB::table('app_requetes')->insert(['cle' => $cle, 'user_id' => $request->user()?->id, 'cree_le' => now()]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new RegleMetier('Cette action a déjà été enregistrée. Rechargez la page pour voir le résultat.');
            }
            throw $e;
        }
    }
}

<?php

namespace App\Support;

use App\Exceptions\RegleMetier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dialogue avec les déclencheurs d'emplacements V1.17 (RG-REP-005, RG-VOL-008).
 * Quand un effectif diminue, les déclencheurs retirent les sujets d'un emplacement ;
 * s'il y a plusieurs origines possibles, l'emplacement de sortie doit être fourni via @sf117_from.
 */
class Lieux
{
    public const VOLAILLE = 'volaille';

    public const REPRO_M = 'repro_m';

    public const REPRO_F = 'repro_f';

    public const OVIN = 'ovin';

    /**
     * Origines possibles pour une sortie : emplacements occupés + quantité non affectée.
     *
     * @return list<array{id:int, nom:string, quantite:int}> id 0 = non affecté
     */
    public static function origines(string $famille, int $idSource, int $effectif): array
    {
        $positions = DB::table('modInfra_positions as p')
            ->leftJoin('batiments_engraissement as b', 'b.id', '=', 'p.id_batiment')
            ->where('p.famille', $famille)->where('p.id_source', $idSource)
            ->orderBy('p.id_batiment')
            ->get(['p.id_batiment', 'p.quantite', 'b.nom_batiment']);
        $out = [];
        $affecte = 0;
        foreach ($positions as $p) {
            $affecte += (int) $p->quantite;
            $out[] = ['id' => (int) $p->id_batiment, 'nom' => $p->nom_batiment ?: 'Emplacement '.$p->id_batiment, 'quantite' => (int) $p->quantite];
        }
        if ($effectif > $affecte) {
            $out[] = ['id' => 0, 'nom' => 'Non affecté à un emplacement', 'quantite' => $effectif - $affecte];
        }

        return $out;
    }

    /** Positionne les variables de session lues par les déclencheurs, pour la transaction en cours. */
    public static function preparer(string $famille, int $idSource, ?int $origine, string $motif): void
    {
        $user = Auth::user();
        DB::statement('SET @sf117_family = ?, @sf117_entity = ?, @sf117_from = ?, @sf117_user = ?, @sf117_reason = ?', [
            $famille, $idSource, $origine, mb_substr($user?->nomComplet() ?? 'application', 0, 150), mb_substr($motif, 0, 500),
        ]);
    }

    public static function reinitialiser(): void
    {
        DB::statement('SET @sf117_family = NULL, @sf117_entity = NULL, @sf117_from = NULL, @sf117_user = NULL, @sf117_reason = NULL');
    }

    /**
     * Exécute une écriture soumise aux déclencheurs et traduit leurs refus (SQLSTATE 45000) en message métier.
     */
    public static function executer(callable $ecriture): mixed
    {
        try {
            return $ecriture();
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '45000') {
                throw new RegleMetier(self::traduire((string) ($e->errorInfo[2] ?? '')));
            }
            throw $e;
        }
    }

    public static function traduire(string $message): string
    {
        $table = [
            'choisir emplacement de sortie' => 'Ces sujets sont répartis sur plusieurs emplacements : choisissez l’emplacement de sortie.',
            'effectif insuffisant dans emplacement choisi' => 'L’emplacement choisi ne contient pas assez de sujets.',
            'quantite non affectee insuffisante' => 'Pas assez de sujets non affectés : choisissez l’emplacement de sortie.',
            'liberer les emplacements' => 'Libérez d’abord les emplacements occupés par ces sujets (module Infrastructures).',
            'repartition superieure a effectif' => 'La répartition par emplacement dépasse l’effectif : un contrôle est nécessaire.',
            'periode cloturee' => 'Cette date appartient à une période comptable clôturée : écriture refusée.',
            'effectif invalide' => 'Effectif invalide pour des sujets affectés à un emplacement.',
            'identifiant animal non modifiable' => 'L’identifiant d’un animal ne peut pas être modifié.',
            'capacite ou statut incompatible' => 'La capacité ou l’état choisi est incompatible avec les sujets présents : déplacez-les d’abord.',
            'vider emplacement avant changement' => 'Videz l’emplacement avant de changer son site, sa famille, son espèce, son stade ou sa disponibilité.',
            'liaison de sortie cloturee' => 'Cette sortie appartient à une période comptable clôturée : liaison refusée.',
        ];
        foreach ($table as $cle => $fr) {
            if (str_contains($message, $cle)) {
                return $fr;
            }
        }

        return 'Opération refusée par un contrôle de la base : '.$message;
    }
}

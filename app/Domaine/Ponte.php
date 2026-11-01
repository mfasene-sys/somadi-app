<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Ponte et validation des ramassages (PON). */
class Ponte
{
    /** RG-PON-002 : taux = arrondi à 2 décimales de 100 × total / femelles. */
    public static function taux(int $total, int $femelles): ?float
    {
        return $femelles > 0 ? round(100 * $total / $femelles, 2) : null;
    }

    public static function statutNormalise(?string $statut): string
    {
        return mb_strtolower(trim((string) $statut));
    }

    /** RG-PON-009 : état affiché d'une ligne de ramassage. */
    public static function etat(object $l): string
    {
        $s = self::statutNormalise($l->statut);
        if ($s === 'attente' && $l->total_oeufs_valides === null && $l->nb_oeufs_defectueux === null) {
            return 'attente';
        }
        $q = [$l->ramassage_matin, $l->ramassage_apres_midi, $l->total_oeufs_valides, $l->nb_oeufs_defectueux];
        $completes = ! in_array(null, $q, true) && min(array_map('intval', $q)) >= 0;
        if ($s === 'valide' && $completes && (int) $l->ramassage_matin + (int) $l->ramassage_apres_midi === (int) $l->total_oeufs_valides + (int) $l->nb_oeufs_defectueux) {
            return 'valide';
        }

        return 'a_verifier';
    }

    /** RG-PON-001 à 005, 010 : saisie ou mise à jour du ramassage d'une bande pour une date. */
    public static function saisir(array $d): int
    {
        $date = Carbon::parse($d['date']);
        if ($date->isAfter(today())) {
            throw new RegleMetier('Choisissez une date valide, au plus tard aujourd’hui.');
        }
        $matin = (int) $d['ramassage_matin'];
        $am = (int) $d['ramassage_apres_midi'];
        if ($matin < 0 || $am < 0) {
            throw new RegleMetier('Renseignez un nombre entier positif ou nul.');
        }

        return DB::transaction(function () use ($d, $date, $matin, $am) {
            $bande = Reproducteurs::verrouiller((int) $d['id_bande']);
            if ($bande->reformee) {
                throw new RegleMetier('La saisie est réservée aux bandes actives.');
            }
            $femelles = (int) $bande->nb_femelles;
            if ($femelles <= 0) {
                throw new RegleMetier('Renseignez un effectif de femelles supérieur à zéro dans la bande avant la saisie.');
            }
            $total = $matin + $am;
            if ($total > $femelles) {
                throw new RegleMetier("Le total des œufs ($total) dépasse le nombre actuel de femelles ($femelles).");
            }
            $jour = $date->toDateString();
            $lignes = DB::table('suivi_ponte')->where('id_bande', $bande->id)->where('date', $jour)->orderBy('id')->lockForUpdate()->get();
            if ($lignes->count() > 1) {
                throw new RegleMetier('Plusieurs ramassages existent pour cette bande et cette date. Aucune ligne n’a été supprimée : vérifiez cet historique.');
            }
            if (DB::table('stock_oeufs_ramasses')->where('id_bande', $bande->id)->where('date_ramassage', $jour)->lockForUpdate()->exists()) {
                throw new RegleMetier('Un stock existe déjà pour cette journée. La saisie ne peut plus être modifiée ici.');
            }
            $valeurs = [
                'ramassage_matin' => $matin,
                'ramassage_apres_midi' => $am,
                'utilisateur' => Format::auteur(),
                'taux_ponte' => self::taux($total, $femelles),
                'temoin' => ! empty($d['temoin']) ? 1 : 0,
            ];
            $existante = $lignes->first();
            if ($existante) {
                if (self::etat($existante) !== 'attente') {
                    throw new RegleMetier('Ce ramassage est validé ou présente un état à vérifier. Il ne peut plus être modifié ici.');
                }
                Empreinte::verifier($existante, $d['empreinte'] ?? null, 'La journée affichée a changé. Rechargez le formulaire.');
                DB::table('suivi_ponte')->where('id', $existante->id)->update($valeurs);
                Journal::enregistrer('ponte.modification', 'PON', 'ramassage', $existante->id, (array) $existante, $valeurs);

                return (int) $existante->id;
            }
            if (! empty($d['empreinte'])) {
                throw new RegleMetier('La journée affichée a changé. Rechargez le formulaire.');
            }
            $id = DB::table('suivi_ponte')->insertGetId($valeurs + ['id_bande' => $bande->id, 'date' => $jour, 'statut' => 'Attente']);
            Journal::enregistrer('ponte.saisie', 'PON', 'ramassage', $id, apres: $valeurs + ['id_bande' => $bande->id, 'date' => $jour]);

            return $id;
        });
    }

    /** RG-PON-006 à 008 : validation, création d'une seule ligne de stock. */
    public static function valider(int $id, int $defectueux, ?string $empreinte): int
    {
        return DB::transaction(function () use ($id, $defectueux, $empreinte) {
            $l = DB::table('suivi_ponte')->where('id', $id)->first() ?? throw new RegleMetier('Enregistrement introuvable.');
            Reproducteurs::verrouiller((int) $l->id_bande);
            $l = DB::table('suivi_ponte')->where('id', $id)->lockForUpdate()->first();
            Empreinte::verifier($l, $empreinte);
            if (self::etat($l) !== 'attente') {
                throw new RegleMetier('Ce ramassage est déjà validé ou présente un état incohérent.');
            }
            if (! $l->date || Carbon::parse($l->date)->isAfter(today())) {
                throw new RegleMetier('La date du ramassage est invalide ou future.');
            }
            if (DB::table('suivi_ponte')->where('id_bande', $l->id_bande)->where('date', $l->date)->count() > 1) {
                throw new RegleMetier('Plusieurs ramassages existent pour cette bande et cette date. Vérifiez les doublons avant validation.');
            }
            if (DB::table('stock_oeufs_ramasses')->where('id_bande', $l->id_bande)->where('date_ramassage', $l->date)->lockForUpdate()->exists()) {
                throw new RegleMetier('Un stock existe déjà pour cette journée. Validation bloquée pour éviter un doublon.');
            }
            $total = (int) $l->ramassage_matin + (int) $l->ramassage_apres_midi;
            if ($defectueux < 0 || $defectueux > $total) {
                throw new RegleMetier('Les œufs défectueux ne peuvent pas dépasser le total ramassé.');
            }
            $valides = $total - $defectueux;
            DB::table('suivi_ponte')->where('id', $id)->update(['statut' => 'valide', 'total_oeufs_valides' => $valides, 'nb_oeufs_defectueux' => $defectueux]);
            $idStock = DB::table('stock_oeufs_ramasses')->insertGetId([
                'date_ramassage' => $l->date,
                'id_bande' => $l->id_bande,
                'total_oeufs' => $valides,
                'statut' => $valides > 0 ? 'attente' : 'affecte',
                'utilisateur' => Format::auteur(),
            ]);
            Journal::enregistrer('ponte.validation', 'PON', 'ramassage', $id, ['statut' => $l->statut], ['valides' => $valides, 'defectueux' => $defectueux, 'stock' => $idStock]);

            return $idStock;
        });
    }
}

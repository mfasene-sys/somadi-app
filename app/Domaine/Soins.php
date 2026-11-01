<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Soins et prophylaxie des volailles (SAV). */
class Soins
{
    public const ETATS = ['planifie' => 'Planifié', 'realise' => 'Réalisé', 'annule' => 'Annulé'];

    public const ORIGINE_OCCASIONNEL = 'Soin Occasionnel';

    public const ORIGINE_PROGRAMME = 'Plan_Poussins_45Jours';

    /** RG-SAV-002, 003 : soin occasionnel, sans doublon actif. */
    public static function ajouterOccasionnel(int $idLot, int $idSoin, string $date, ?string $observation): int
    {
        return DB::transaction(function () use ($idLot, $idSoin, $date, $observation) {
            $lot = LotsVolailles::verrouiller($idLot);
            if ($lot->statut !== 'actif') {
                throw new RegleMetier('Choisissez un lot actif.');
            }
            $soin = DB::table('modSoinVacc_catalogue')->where('id', $idSoin)->first() ?? throw new RegleMetier('Le soin sélectionné n’existe plus dans le catalogue.');
            $jour = Carbon::parse($date)->toDateString();
            if (self::doublon($idLot, $jour, $idSoin)) {
                throw new RegleMetier('Ce soin du catalogue est déjà planifié ou réalisé pour ce lot à cette date. Consultez le planning.');
            }
            $id = DB::table('modSoinVacc_soins')->insertGetId(self::copie($idLot, $jour, $soin) + ['observation' => $observation, 'origine' => self::ORIGINE_OCCASIONNEL]);
            Journal::enregistrer('soin.ajout', 'SAV', 'soin', $id, apres: ['lot' => $idLot, 'soin' => $soin->nom, 'date' => $jour]);

            return $id;
        });
    }

    private static function doublon(int $idLot, string $jour, int $idSoin): bool
    {
        return DB::table('modSoinVacc_soins')->where(['id_lot' => $idLot, 'date_prevue' => $jour, 'id_soin' => $idSoin])
            ->whereNull('origine_plan_id')->whereIn('origine', [self::ORIGINE_OCCASIONNEL, self::ORIGINE_PROGRAMME])
            ->whereIn('etat', ['planifie', 'realise'])->lockForUpdate()->exists();
    }

    private static function copie(int $idLot, string $jour, object $soin): array
    {
        return [
            'id_lot' => $idLot, 'date_prevue' => $jour, 'id_soin' => $soin->id, 'type' => $soin->type, 'produit' => $soin->produit_utilise,
            'dose' => $soin->dose, 'voie' => $soin->voie_administration, 'etat' => 'planifie',
        ];
    }

    /** RG-VOL-010, RG-SAV-008 : programme poussins recopié aux dates d'entrée + jour du programme. */
    public static function genererProgrammePoussins(int $idLot, string $espece, string $dateDepart): int
    {
        $lignes = DB::table('modSoinVacc_programme_poussins')->whereRaw('LOWER(espece) = ?', [mb_strtolower($espece)])->orderBy('jour_post_eclosion')->get();
        if ($lignes->isEmpty()) {
            throw new RegleMetier('Aucun programme de soins n’est défini pour cette espèce. Décochez la génération ou complétez le programme.');
        }
        $catalogue = DB::table('modSoinVacc_catalogue')->whereIn('id', $lignes->pluck('id_soin')->filter())->get()->keyBy('id');
        foreach ($lignes as $l) {
            if (! $l->id_soin || ! $catalogue->has($l->id_soin) || (int) $l->jour_post_eclosion < 0) {
                throw new RegleMetier('Une ligne du programme de soins est incomplète. Corrigez le programme ou décochez sa génération.');
            }
        }
        $depart = Carbon::parse($dateDepart);
        $n = 0;
        foreach ($lignes as $l) {
            $jour = $depart->copy()->addDays((int) $l->jour_post_eclosion)->toDateString();
            if (self::doublon($idLot, $jour, (int) $l->id_soin)) {
                continue;
            }
            DB::table('modSoinVacc_soins')->insert(self::copie($idLot, $jour, $catalogue[$l->id_soin]) + [
                'observation' => $l->commentaire, 'origine' => self::ORIGINE_PROGRAMME,
            ]);
            $n++;
        }

        return $n;
    }

    /** Bouton « générer le programme » d'un lot existant. */
    public static function programmePourLot(int $idLot): int
    {
        return DB::transaction(function () use ($idLot) {
            $lot = LotsVolailles::verrouiller($idLot);
            if ($lot->statut !== 'actif' || ! $lot->date_entree) {
                throw new RegleMetier('Le programme ne peut être généré que pour un lot actif ayant une date d’entrée.');
            }
            $n = self::genererProgrammePoussins($idLot, (string) $lot->espece, $lot->date_entree);
            Journal::enregistrer('soin.programme', 'SAV', 'lot', $idLot, apres: ['soins_crees' => $n]);

            return $n;
        });
    }

    /** RG-SAV-004 à 007 : changement d'état réalisé / annulé. */
    public static function changerEtat(int $id, string $etat, ?string $commentaire, string $empreinte, ?int $lotFiltre = null): void
    {
        if (! in_array($etat, ['realise', 'annule'], true)) {
            throw new RegleMetier('Choisissez Réalisé ou Annulé.');
        }
        if ($commentaire !== null && strlen($commentaire) > 4000) {
            throw new RegleMetier('Le commentaire de changement est trop long.');
        }
        DB::transaction(function () use ($id, $etat, $commentaire, $empreinte, $lotFiltre) {
            $s = DB::table('modSoinVacc_soins')->where('id', $id)->lockForUpdate()->first();
            if (! $s || ($lotFiltre && (int) $s->id_lot !== $lotFiltre)) {
                throw new RegleMetier('Cette intervention est introuvable dans le lot sélectionné.');
            }
            Empreinte::verifier($s, $empreinte, 'Cette intervention a changé depuis l’ouverture de la page. Relisez son état actuel avant de recommencer.');
            if (self::verrouille($s)) {
                throw new RegleMetier('Modification bloquée : le dernier changement d’état date de plus de 24 heures ou l’état est non reconnu.');
            }
            if ($s->etat === $etat) {
                throw new RegleMetier('Cette intervention possède déjà cet état.');
            }
            $trace = '['.now()->format('d/m/Y H:i').'] '.Format::auteur().' : '.(self::ETATS[$s->etat] ?? $s->etat).' → '.self::ETATS[$etat].($commentaire ? ' — '.$commentaire : '');
            $obs = trim(($s->observation ? $s->observation."\n" : '').$trace);
            if (strlen($obs) > 65000) {
                throw new RegleMetier('L’historique est trop long pour ajouter une nouvelle trace. Contactez l’administrateur.');
            }
            DB::table('modSoinVacc_soins')->where('id', $id)->update(['etat' => $etat, 'date_changement_etat' => now(), 'observation' => $obs]);
            Journal::enregistrer('soin.etat', 'SAV', 'soin', $id, ['etat' => $s->etat], ['etat' => $etat], $commentaire);
        });
    }

    /** RG-SAV-005 : verrou à 24 h après le dernier changement ; état inconnu = verrouillé. */
    public static function verrouille(object $s): bool
    {
        if (! array_key_exists((string) $s->etat, self::ETATS)) {
            return true;
        }
        if ($s->etat === 'planifie' || ! $s->date_changement_etat) {
            return false;
        }

        return Carbon::parse($s->date_changement_etat)->diffInSeconds(now(), false) > 86400;
    }

    public static function enregistrerCatalogue(?int $id, array $d): int
    {
        return DB::transaction(function () use ($id, $d) {
            $v = ['nom' => $d['nom'], 'type' => $d['type'], 'produit_utilise' => $d['produit_utilise'] ?? null, 'dose' => $d['dose'] ?? null,
                'voie_administration' => $d['voie_administration'] ?? null, 'espece_cible' => $d['espece_cible'] ?? null, 'observation' => $d['observation'] ?? null];
            if ($id) {
                $avant = DB::table('modSoinVacc_catalogue')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Soin introuvable.');
                DB::table('modSoinVacc_catalogue')->where('id', $id)->update($v);
                Journal::enregistrer('catalogue_soin.modification', 'SAV', 'catalogue_soin', $id, (array) $avant, $v);

                return $id;
            }
            $id = DB::table('modSoinVacc_catalogue')->insertGetId($v);
            Journal::enregistrer('catalogue_soin.creation', 'SAV', 'catalogue_soin', $id, apres: $v);

            return $id;
        });
    }
}

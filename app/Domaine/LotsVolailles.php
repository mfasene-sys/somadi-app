<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Lieux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Lots volailles et mouvements (VOL). */
class LotsVolailles
{
    public const TYPES = ['entrée' => 'Entrée', 'vente' => 'Vente', 'sortie' => 'Sortie', 'mortalité' => 'Mortalité'];

    /** RG-VOL-011 : réglages thermiques stockés par l'application (°C par tranche de jours). */
    public const THERMO = [[0, 6, 34.0], [7, 13, 31.0], [14, 20, 29.0], [21, 27, 27.0]];

    /** RG-VOL-001, 002, 010, 011 */
    public static function creer(array $d, bool $avecProgramme = false): int
    {
        $initial = (int) $d['nombre_initial'];
        if ($initial <= 0) {
            throw new RegleMetier('Indiquez un nombre entier de sujets supérieur à zéro.');
        }

        return DB::transaction(function () use ($d, $initial, $avecProgramme) {
            $valeurs = [
                'nom_lot' => $d['nom_lot'], 'espece' => $d['espece'], 'race' => $d['race'],
                'nombre_initial' => $initial, 'date_entree' => $d['date_entree'], 'id_plan' => $d['id_plan'] ?? null,
                'origine' => $d['origine'] ?? null, 'remarques' => $d['remarques'] ?? null, 'statut' => 'actif',
                'effectif_en_cours' => $initial, 'id_lot_incubation_source' => $d['id_lot_incubation_source'] ?? null,
            ];
            $id = DB::table('modLotVol_lots_volailles')->insertGetId($valeurs);
            if ($avecProgramme) {
                Soins::genererProgrammePoussins($id, (string) $d['espece'], (string) $d['date_entree']);
                self::genererThermo($id, (string) $d['date_entree']);
            }
            Journal::enregistrer('lot.creation', 'VOL', 'lot', $id, apres: $valeurs + ['programme_soins' => $avecProgramme]);

            return $id;
        });
    }

    public static function genererThermo(int $idLot, string $dateEntree): void
    {
        $debut = Carbon::parse($dateEntree);
        $lignes = [];
        foreach (self::THERMO as [$de, $a, $t]) {
            for ($j = $de; $j <= $a; $j++) {
                $lignes[] = [
                    'id_lot' => $idLot, 'date_prevue' => $debut->copy()->addDays($j)->toDateString(), 'temperature_cible' => $t,
                    'tolerance' => 1.0, 'commentaire' => "Jour $j", 'created_by' => Format::auteur(),
                ];
            }
        }
        DB::table('modSoinVacc_Thermo_reglages')->insert($lignes);
    }

    /** RG-VOL-002 : modifier les informations générales ne recalcule jamais l'effectif courant. */
    public static function modifier(int $id, array $d, string $empreinte): void
    {
        DB::transaction(function () use ($id, $d, $empreinte) {
            $lot = self::verrouiller($id);
            Empreinte::verifier($lot, $empreinte);
            $valeurs = [
                'nom_lot' => $d['nom_lot'], 'espece' => $d['espece'], 'race' => $d['race'], 'nombre_initial' => (int) $d['nombre_initial'],
                'date_entree' => $d['date_entree'], 'id_plan' => $d['id_plan'] ?? null, 'origine' => $d['origine'] ?? null, 'remarques' => $d['remarques'] ?? null,
            ];
            Lieux::executer(fn () => DB::table('modLotVol_lots_volailles')->where('id', $id)->update($valeurs));
            Journal::enregistrer('lot.modification', 'VOL', 'lot', $id, (array) $lot, $valeurs);
        });
    }

    /** RG-VOL-003 à 005 : mouvement, effectif et répartition physique dans une seule transaction. */
    /**
     * @param  bool  $creerACompleter  une sortie « vente » saisie ici crée une vente à compléter ; une vente directe (EC06) passe false.
     */
    public static function mouvement(int $id, array $d, bool $creerACompleter = true): ?int
    {
        $q = (int) $d['quantite'];
        if ($q <= 0) {
            throw new RegleMetier('La quantité doit être un entier strictement positif.');
        }
        if (Carbon::parse($d['date_mouvement'])->isAfter(today())) {
            throw new RegleMetier('La date du mouvement ne peut pas être future.');
        }
        if (trim((string) ($d['commentaire'] ?? '')) === '') {
            throw new RegleMetier('Le motif du mouvement est obligatoire.');
        }

        return DB::transaction(function () use ($id, $d, $q, $creerACompleter) {
            $lot = self::verrouiller($id);
            if ($lot->statut !== 'actif') {
                throw new RegleMetier('Lot archivé : il ne peut plus recevoir de mouvement.');
            }
            $avant = (int) $lot->effectif_en_cours;
            $apres = $d['type_mouvement'] === 'entrée' ? $avant + $q : $avant - $q;
            if ($apres < 0) {
                throw new RegleMetier("Quantité supérieure à l’effectif disponible ($avant).");
            }
            $jour = Carbon::parse($d['date_mouvement'])->toDateString();
            $idMvt = Lieux::executer(fn () => DB::table('modLotVol_mouvements')->insertGetId([
                'id_lot' => $id, 'date_mouvement' => $jour, 'type_mouvement' => $d['type_mouvement'], 'quantite' => $q,
                'commentaire' => $d['commentaire'], 'utilisateur' => Format::auteur(),
            ]));
            $origine = isset($d['origine']) && $d['origine'] !== '' ? (int) $d['origine'] : null;
            Lieux::preparer(Lieux::VOLAILLE, $id, $origine, $d['commentaire']);
            Lieux::executer(fn () => DB::table('modLotVol_lots_volailles')->where('id', $id)->update(['effectif_en_cours' => $apres]));
            Lieux::reinitialiser();

            $aCompleter = null;
            if ($d['type_mouvement'] === 'vente' && $creerACompleter) {
                $aCompleter = Lieux::executer(fn () => DB::table('modVente_Ventes_A_Completer')->insertGetId([
                    'date_vente' => $jour, 'quantite' => $q, 'nom_lot' => mb_substr((string) $lot->nom_lot, 0, 100),
                    'commentaire' => $d['commentaire'], 'utilisateur' => Format::auteur(), 'statut' => 'attente', 'id_lot' => $id,
                ]));
            }
            Journal::enregistrer('lot.mouvement', 'VOL', 'lot', $id, ['effectif' => $avant], ['effectif' => $apres, 'mouvement' => $idMvt, 'vente_a_completer' => $aCompleter] + $d, $d['commentaire']);

            return $aCompleter;
        });
    }

    /** RG-VOL-009 : archivage ou réactivation (statut uniquement, l'historique est conservé). */
    public static function changerStatut(int $id, string $statut): void
    {
        DB::transaction(function () use ($id, $statut) {
            $lot = self::verrouiller($id);
            Lieux::executer(fn () => DB::table('modLotVol_lots_volailles')->where('id', $id)->update(['statut' => $statut]));
            Journal::enregistrer($statut === 'archive' ? 'lot.archivage' : 'lot.reactivation', 'VOL', 'lot', $id, ['statut' => $lot->statut], ['statut' => $statut]);
        });
    }

    /** EC10 */
    public static function exclureDesAnalyses(int $id, bool $exclu, ?string $motif): void
    {
        DB::transaction(function () use ($id, $exclu, $motif) {
            self::verrouiller($id);
            $avant = DB::table('app_lots_options')->where('id_lot', $id)->first();
            DB::table('app_lots_options')->updateOrInsert(['id_lot' => $id], ['exclu_analyses' => $exclu, 'motif_exclusion' => $motif, 'updated_at' => now(), 'created_at' => $avant->created_at ?? now()]);
            Journal::enregistrer('lot.exclusion_analyses', 'POI', 'lot', $id, $avant ? (array) $avant : null, ['exclu' => $exclu, 'motif' => $motif]);
        });
    }

    public static function verrouiller(int $id): object
    {
        return DB::table('modLotVol_lots_volailles')->where('id', $id)->lockForUpdate()->first()
            ?? throw new RegleMetier('Lot introuvable.');
    }

    /** Âge en jours à une date. */
    public static function age(object $lot, ?string $date = null): ?int
    {
        return $lot->date_entree ? (int) Carbon::parse($lot->date_entree)->diffInDays(Carbon::parse($date ?? today()), false) : null;
    }
}

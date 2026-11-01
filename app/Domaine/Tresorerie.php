<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Lieux;
use App\Support\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Trésorerie : opérations, encaissements et invalidations (CPT). */
class Tresorerie
{
    public const SOURCE_VENTE = 'modVente_Ventes';

    public static function categories(string $type): array
    {
        return config('somadi.categories.'.$type, []);
    }

    public static function moyens(): array
    {
        return config('somadi.moyens_paiement');
    }

    /** RG-CPT-001 à 003 : opération autonome (recette ou dépense). */
    public static function creerOperation(array $d): int
    {
        if (! in_array($d['type_op'], ['recette', 'depense'], true)) {
            throw new RegleMetier('Type d’opération invalide.');
        }
        if (! in_array($d['categorie'], self::categories($d['type_op']), true)) {
            throw new RegleMetier('Catégorie incompatible avec le type d’opération.');
        }
        if (! in_array($d['moyen_paiement'], self::moyens(), true)) {
            throw new RegleMetier('Moyen de paiement invalide.');
        }
        $montant = Montant::saisie($d['montant']);
        if ($montant <= 0) {
            throw new RegleMetier('Le montant doit être strictement positif.');
        }
        if (Carbon::parse($d['date_op'])->isAfter(today())) {
            throw new RegleMetier('La date de l’opération ne peut pas être future.');
        }

        return DB::transaction(function () use ($d, $montant) {
            $v = [
                'date_op' => Carbon::parse($d['date_op'])->toDateString(), 'type_op' => $d['type_op'], 'categorie' => $d['categorie'],
                'utilisateur' => Format::auteur(), 'libelle' => mb_substr(trim($d['libelle']), 0, 255), 'montant' => Montant::versBase($montant),
                'moyen_paiement' => $d['moyen_paiement'], 'observations' => $d['observations'] ?? null, 'est_valide' => 1, 'invalidated_comment' => '',
            ];
            $id = Lieux::executer(fn () => DB::table('modCompta_operations')->insertGetId($v));
            Journal::enregistrer('tresorerie.operation', 'CPT', 'operation', $id, apres: $v);

            return $id;
        });
    }

    /** Somme des recettes valides liées à une vente, moins les dépenses liées (centimes). */
    public static function encaisse(int $idVente, bool $verrou = false): array
    {
        $ops = DB::table('modCompta_operations')->whereRaw('LOWER(TRIM(source_table)) = ?', [mb_strtolower(self::SOURCE_VENTE)])
            ->where('source_id', $idVente)->where('est_valide', 1)->when($verrou, fn ($q) => $q->lockForUpdate())->get();

        return [
            'recettes' => $ops->where('type_op', 'recette')->sum(fn ($o) => Montant::base($o->montant)),
            'depenses' => $ops->where('type_op', 'depense')->count(),
            'operations' => $ops,
        ];
    }

    /**
     * RG-CPT-005 : contrôle de cohérence d'une vente avant tout règlement.
     *
     * @return array{ok:bool, message:?string, total:int, encaisse:int, reste:int, reste_calcule:int}
     */
    public static function audit(object $vente, bool $verrou = false): array
    {
        $total = Montant::base($vente->montant_total);
        $reste = Montant::base($vente->montant_reste_encaisser);
        $e = self::encaisse((int) $vente->id, $verrou);
        $calcule = $total - $e['recettes'];
        $message = null;
        if ($e['depenses'] > 0) {
            $message = 'Une dépense est liée à cette vente : à examiner dans les rapprochements.';
        } elseif (Montant::produit((int) $vente->quantite, Montant::base($vente->prix_unitaire)) !== $total) {
            $message = 'Le total de la vente ne correspond pas à quantité × prix unitaire.';
        } elseif ($calcule !== $reste) {
            $message = 'Le reste à encaisser enregistré ('.Montant::fcfa($reste).') ne correspond pas aux règlements liés ('.Montant::fcfa($calcule).' attendu) : un rapprochement est nécessaire.';
        } elseif ($reste < 0 || $reste > $total) {
            $message = 'Reste à encaisser hors limites.';
        }

        return ['ok' => $message === null, 'message' => $message, 'total' => $total, 'encaisse' => $e['recettes'], 'reste' => $reste, 'reste_calcule' => $calcule];
    }

    /** RG-CPT-004 à 007 : encaissement total ou partiel d'une vente. */
    public static function encaisser(int $idVente, array $d): int
    {
        return DB::transaction(function () use ($idVente, $d) {
            $vente = DB::table('modVente_Ventes')->where('id', $idVente)->lockForUpdate()->first() ?? throw new RegleMetier('Vente introuvable.');
            $audit = self::audit($vente, true);
            if (! $audit['ok']) {
                throw new RegleMetier('Encaissement bloqué : '.$audit['message']);
            }
            if ($audit['reste'] <= 0) {
                throw new RegleMetier('Cette vente est déjà soldée.');
            }
            $date = Carbon::parse($d['date_op']);
            if ($date->isAfter(today())) {
                throw new RegleMetier('La date du paiement ne peut pas être future.');
            }
            if ($date->lt(Carbon::parse($vente->date_vente))) {
                throw new RegleMetier('La date du paiement ne peut pas précéder la vente ('.Format::date($vente->date_vente).').');
            }
            if (! in_array($d['moyen_paiement'] ?? '', self::moyens(), true)) {
                throw new RegleMetier('Choisissez un moyen de paiement.');
            }
            $montant = ($d['mode'] ?? 'total') === 'total' ? $audit['reste'] : Montant::saisie($d['montant'] ?? '', 'Le montant encaissé');
            if ($montant <= 0) {
                throw new RegleMetier('Le montant encaissé doit être strictement positif.');
            }
            if ($montant > $audit['reste']) {
                throw new RegleMetier('Le montant dépasse le reste à encaisser ('.Montant::fcfa($audit['reste']).').');
            }
            $categorie = $d['categorie'] ?? null;
            if (! in_array($categorie, self::categories('recette'), true)) {
                $categorie = config('somadi.produits.'.$vente->espece.'.categorie', 'Autres recettes');
            }
            // L'opération est insérée avant la mise à jour de la vente : le déclencheur de clôture recalcule le solde à partir des opérations.
            $id = Lieux::executer(fn () => DB::table('modCompta_operations')->insertGetId([
                'date_op' => $date->toDateString(), 'type_op' => 'recette', 'categorie' => $categorie, 'utilisateur' => Format::auteur(),
                'libelle' => mb_substr('Encaissement vente n° '.$vente->id.' – '.$vente->client_nom, 0, 255), 'montant' => Montant::versBase($montant),
                'moyen_paiement' => $d['moyen_paiement'], 'observations' => $d['observations'] ?? null, 'source_table' => self::SOURCE_VENTE,
                'source_id' => $vente->id, 'est_valide' => 1, 'date_vente' => $vente->date_vente, 'invalidated_comment' => '', 'id_lot' => $vente->id_lot,
            ]));
            $reste = $audit['reste'] - $montant;
            Lieux::executer(fn () => DB::table('modVente_Ventes')->where('id', $vente->id)->update([
                'montant_reste_encaisser' => Montant::versBase($reste), 'statut' => $reste === 0 ? 'Encaissé' : 'A encaisser',
            ]));
            Journal::enregistrer('tresorerie.encaissement', 'CPT', 'vente', $vente->id, ['reste' => $audit['reste']], ['reste' => $reste, 'operation' => $id, 'montant' => $montant]);

            return $id;
        });
    }

    /** RG-CPT-008 à 010 : invalidation motivée (jamais de suppression). */
    public static function invalider(int $idOp, string $motif): void
    {
        if (mb_strlen(trim($motif)) < 3) {
            throw new RegleMetier('Le motif d’invalidation doit compter au moins 3 caractères.');
        }
        DB::transaction(function () use ($idOp, $motif) {
            $op = DB::table('modCompta_operations')->where('id', $idOp)->lockForUpdate()->first() ?? throw new RegleMetier('Opération introuvable.');
            if ((int) $op->est_valide !== 1) {
                throw new RegleMetier('Cette opération est déjà invalidée.');
            }
            $source = mb_strtolower(trim((string) $op->source_table));
            if ($source !== '' && $source !== mb_strtolower(self::SOURCE_VENTE)) {
                throw new RegleMetier('Cette opération dépend d’un autre module : corrigez-la depuis ce module.');
            }
            $vente = $source !== '' ? DB::table('modVente_Ventes')->where('id', $op->source_id)->lockForUpdate()->first() : null;
            Lieux::executer(fn () => DB::table('modCompta_operations')->where('id', $idOp)->update([
                'est_valide' => 0, 'invalidated_at' => now(), 'invalidated_comment' => trim($motif), 'invalidated_by' => mb_substr(Format::auteur(), 0, 50),
            ]));
            $apresVente = null;
            if ($vente && $op->type_op === 'recette') {
                $total = Montant::base($vente->montant_total);
                $reste = min($total, Montant::base($vente->montant_reste_encaisser) + Montant::base($op->montant));
                Lieux::executer(fn () => DB::table('modVente_Ventes')->where('id', $vente->id)->update([
                    'montant_reste_encaisser' => Montant::versBase($reste), 'statut' => $reste === 0 ? 'Encaissé' : 'A encaisser',
                ]));
                $apresVente = ['vente' => $vente->id, 'reste' => $reste];
            }
            Journal::enregistrer('tresorerie.invalidation', 'CPT', 'operation', $idOp, (array) $op, ['est_valide' => 0, 'vente' => $apresVente], trim($motif));
        });
    }

    /** Récapitulatif annuel par mois (centimes). */
    public static function recapitulatif(int $annee): array
    {
        $lignes = DB::table('modCompta_operations')->where('est_valide', 1)->whereYear('date_op', $annee)
            ->groupBy(DB::raw('MONTH(date_op)'), 'type_op')->select(DB::raw('MONTH(date_op) mois'), 'type_op', DB::raw('SUM(montant) total'))->get();
        $mois = [];
        for ($m = 1; $m <= 12; $m++) {
            $mois[$m] = ['recette' => 0, 'depense' => 0];
        }
        foreach ($lignes as $l) {
            $mois[(int) $l->mois][$l->type_op] = Montant::base($l->total);
        }
        $categories = DB::table('modCompta_operations')->where('est_valide', 1)->whereYear('date_op', $annee)
            ->groupBy('type_op', 'categorie')->orderBy('type_op')->orderByDesc(DB::raw('SUM(montant)'))
            ->select('type_op', 'categorie', DB::raw('SUM(montant) total'), DB::raw('COUNT(*) n'))->get()
            ->map(fn ($c) => ['type' => $c->type_op, 'categorie' => $c->categorie, 'total' => Montant::base($c->total), 'n' => (int) $c->n]);

        return ['mois' => $mois, 'categories' => $categories];
    }
}

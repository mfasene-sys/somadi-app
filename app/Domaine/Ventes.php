<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Lieux;
use App\Support\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Ventes générales, finalisation des sorties et ventes d'œufs (VEN). */
class Ventes
{
    public static function produits(): array
    {
        return config('somadi.produits');
    }

    public static function libelle(?string $code): string
    {
        return self::produits()[$code]['libelle'] ?? ($code ? $code.' (ancien libellé)' : '—');
    }

    /** EC07 : un seul contrôle du téléphone, PC et mobile : 8 à 15 chiffres, « + » facultatif. */
    public static function telephone(string $tel): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $tel));
        if (! preg_match('/^\+?[0-9 ]+$/', $t) || strlen(preg_replace('/\D/', '', $t)) < 8 || strlen(preg_replace('/\D/', '', $t)) > 15 || strlen($t) > 20) {
            throw new RegleMetier('Téléphone invalide : 8 à 15 chiffres, « + » facultatif.');
        }

        return $t;
    }

    /** RG-VEN-003 à 005 : contrôles communs et insertion d'une vente (créance initiale = total). */
    private static function inserer(array $d, string $produit, int $quantite, ?int $idLot): object
    {
        $date = Carbon::parse($d['date_vente']);
        if ($date->isAfter(today())) {
            throw new RegleMetier('La date de vente ne peut pas être future.');
        }
        $client = trim((string) $d['client_nom']);
        if ($client === '' || mb_strlen($client) > 100) {
            throw new RegleMetier('Renseignez le nom du client (100 caractères maximum).');
        }
        if ($quantite <= 0) {
            throw new RegleMetier('La quantité doit être un entier strictement positif.');
        }
        $prix = Montant::saisie($d['prix_unitaire'], 'Le prix unitaire');
        if ($prix <= 0) {
            throw new RegleMetier('Le prix unitaire doit être strictement positif.');
        }
        $total = Montant::produit($quantite, $prix);
        $v = [
            'date_vente' => $date->toDateString(), 'client_nom' => $client, 'client_tel' => self::telephone((string) $d['client_tel']),
            'espece' => $produit, 'quantite' => $quantite, 'prix_unitaire' => Montant::versBase($prix), 'montant_total' => Montant::versBase($total),
            'commentaire' => $d['commentaire'] ?? null, 'utilisateur' => Format::auteur(), 'montant_reste_encaisser' => Montant::versBase($total),
            'statut' => 'A encaisser', 'id_lot' => $idLot,
        ];
        $id = Lieux::executer(fn () => DB::table('modVente_Ventes')->insertGetId($v));

        return (object) (['id' => $id] + $v);
    }

    /** RG-OVM-008 : vente générale d'un ovin (quantité 1, sans lot volaille, reste intégral). */
    public static function creerOvin(array $d): object
    {
        return self::inserer($d, 'ovin', 1, null);
    }

    /** Encaissement immédiat facultatif (vente sur le terrain), dans la même transaction. */
    private static function encaissementImmediat(object $vente, array $d): void
    {
        $mode = $d['encaissement'] ?? 'aucun';
        if ($mode === 'aucun' || $mode === null) {
            return;
        }
        Tresorerie::encaisser($vente->id, [
            'mode' => $mode === 'total' ? 'total' : 'partiel', 'montant' => $d['montant_encaisse'] ?? null,
            'date_op' => $vente->date_vente, 'moyen_paiement' => $d['moyen_paiement'] ?? null,
        ]);
    }

    /** Vente générale. EC06 : liée à un lot, elle crée la sortie et baisse l'effectif dans la même opération. */
    public static function creer(array $d): int
    {
        $produit = (string) $d['produit'];
        $p = self::produits()[$produit] ?? null;
        if (! $p) {
            throw new RegleMetier('Produit inconnu.');
        }
        if (! $p['general']) {
            throw new RegleMetier(str_starts_with($produit, 'oeufs') ? 'Les œufs se vendent depuis leurs lots (onglet « Vente d’œufs »), pour tracer le stock.' : 'Un ovin se vend par une sortie animale (module Ovins).');
        }

        return DB::transaction(function () use ($d, $produit) {
            $idLot = ! empty($d['id_lot']) ? (int) $d['id_lot'] : null;
            $quantite = (int) $d['quantite'];
            $vente = self::inserer($d, $produit, $quantite, $idLot);
            if ($idLot) {
                LotsVolailles::mouvement($idLot, [
                    'type_mouvement' => 'vente', 'quantite' => $quantite, 'date_mouvement' => $vente->date_vente,
                    'commentaire' => mb_substr("Vente n° {$vente->id} – {$vente->client_nom}", 0, 1000), 'origine' => $d['origine'] ?? null,
                ], false);
            }
            self::encaissementImmediat($vente, $d);
            Journal::enregistrer('vente.creation', 'VEN', 'vente', $vente->id, apres: (array) $vente);

            return $vente->id;
        });
    }

    /** RG-VEN-006 : finalisation d'une sortie à compléter, sans second débit d'effectif. */
    public static function finaliser(int $idSortie, array $d): int
    {
        return DB::transaction(function () use ($idSortie, $d) {
            $s = DB::table('modVente_Ventes_A_Completer')->where('id', $idSortie)->lockForUpdate()->first() ?? throw new RegleMetier('Sortie introuvable.');
            if (mb_strtolower(trim((string) $s->statut)) !== 'attente') {
                throw new RegleMetier('Cette sortie a déjà été traitée.');
            }
            if ((int) $d['quantite'] !== (int) $s->quantite) {
                throw new RegleMetier("La quantité doit être celle de la sortie ({$s->quantite}).");
            }
            $produit = (string) $d['produit'];
            if (! (self::produits()[$produit]['general'] ?? false)) {
                throw new RegleMetier('Produit inconnu ou non vendable ici.');
            }
            $vente = self::inserer(['date_vente' => $s->date_vente] + $d, $produit, (int) $s->quantite, $s->id_lot ? (int) $s->id_lot : null);
            Lieux::executer(fn () => DB::table('modVente_Ventes_A_Completer')->where('id', $idSortie)->update(['statut' => 'completee', 'date_traitement' => today()->toDateString()]));
            self::encaissementImmediat($vente, $d);
            Journal::enregistrer('vente.finalisation', 'VEN', 'vente', $vente->id, ['sortie' => (array) $s], (array) $vente);

            return $vente->id;
        });
    }

    /**
     * Disponible commercial d'un lot d'œufs (RG-VEN-010, 011).
     *
     * @return array{disponible:int, vendu:int, coherent:bool, message:?string}
     */
    public static function disponibleOeufs(object $lot): array
    {
        $vendu = (int) DB::table('modVente_oeufs_affectations')->where('id_lot_oeufs', $lot->id)->sum('quantite');
        $q = (int) $lot->quantite;
        if ($lot->statut === 'vendu' && $vendu === 0) {
            return ['disponible' => 0, 'vendu' => 0, 'coherent' => true, 'message' => 'Vendu avant la liaison V1.8'];
        }
        if ($vendu > $q || ($lot->statut === 'vendu' && $vendu !== $q) || ($lot->statut === 'nouveau' && $vendu >= $q && $q > 0)) {
            return ['disponible' => 0, 'vendu' => $vendu, 'coherent' => false, 'message' => 'Statut ou affectations incohérents'];
        }

        return ['disponible' => $lot->statut === 'nouveau' ? $q - $vendu : 0, 'vendu' => $vendu, 'coherent' => true, 'message' => null];
    }

    /** RG-VEN-008 à 012 : vente d'œufs affectée en FIFO sur les lots sélectionnés. */
    public static function vendreOeufs(array $d): int
    {
        $produit = (string) $d['produit'];
        $espece = self::produits()[$produit]['espece'] ?? null;
        if (! str_starts_with($produit, 'oeufs') || ! $espece) {
            throw new RegleMetier('Choisissez un produit « œufs ».');
        }
        $ids = array_values(array_unique(array_map('intval', $d['lots'] ?? [])));
        if (count($ids) < 1 || count($ids) > 50) {
            throw new RegleMetier('Sélectionnez de 1 à 50 lots d’œufs.');
        }
        $quantite = (int) $d['quantite'];

        return DB::transaction(function () use ($d, $produit, $espece, $ids, $quantite) {
            $lots = DB::table('lots_oeufs_ramasses as l')->leftJoin('stock_oeufs_ramasses as s', 's.id', '=', 'l.id_stock_oeufs')
                ->leftJoin('bandes_parentales as b', 'b.id', '=', 'l.id_bande')->whereIn('l.id', $ids)
                ->orderBy('s.date_ramassage')->orderBy('l.id')->lockForUpdate()->get(['l.*', 's.date_ramassage', 's.id_bande as stock_bande', 'b.espece']);
            if ($lots->count() !== count($ids)) {
                throw new RegleMetier('Un lot sélectionné n’existe plus.');
            }
            DB::table('modVente_oeufs_affectations')->whereIn('id_lot_oeufs', $ids)->lockForUpdate()->get();
            $dateVente = Carbon::parse($d['date_vente'])->toDateString();
            $dispo = [];
            foreach ($lots as $l) {
                if ($l->type_lot !== 'vendre' || $l->espece !== $espece || (int) $l->stock_bande !== (int) $l->id_bande) {
                    throw new RegleMetier("Le lot n° {$l->id} n’est pas un lot « À vendre » de ".($espece === 'caille' ? 'caille' : 'poulet').'.');
                }
                $r = DB::table('suivi_ponte')->where('id_bande', $l->id_bande)->where('date', $l->date_ramassage)->get();
                if ($r->count() !== 1 || Ponte::etat($r->first()) !== 'valide') {
                    throw new RegleMetier("Le ramassage du lot n° {$l->id} n’est pas validé ou est incohérent.");
                }
                if ($l->date_ramassage > $dateVente) {
                    throw new RegleMetier("La vente ne peut pas précéder la collecte du lot n° {$l->id}.");
                }
                $x = self::disponibleOeufs($l);
                if (! $x['coherent']) {
                    throw new RegleMetier("Lot n° {$l->id} : {$x['message']}. Vente bloquée.");
                }
                $dispo[$l->id] = $x;
            }
            $total = array_sum(array_column($dispo, 'disponible'));
            if ($quantite > $total) {
                throw new RegleMetier("Quantité supérieure au disponible des lots sélectionnés ($total).");
            }
            $vente = self::inserer($d, $produit, $quantite, null);
            $reste = $quantite;
            $affectations = [];
            foreach ($lots as $l) {
                if ($reste === 0) {
                    break;
                }
                $prend = min($reste, $dispo[$l->id]['disponible']);
                if ($prend <= 0) {
                    continue;
                }
                Lieux::executer(fn () => DB::table('modVente_oeufs_affectations')->insert([
                    'id_vente' => $vente->id, 'id_lot_oeufs' => $l->id, 'quantite' => $prend, 'utilisateur' => Format::auteur(),
                ]));
                if ($dispo[$l->id]['vendu'] + $prend === (int) $l->quantite) {
                    DB::table('lots_oeufs_ramasses')->where('id', $l->id)->update(['statut' => 'vendu']);
                }
                $affectations[$l->id] = $prend;
                $reste -= $prend;
            }
            self::encaissementImmediat($vente, $d);
            Journal::enregistrer('vente.oeufs', 'VEN', 'vente', $vente->id, apres: (array) $vente + ['affectations' => $affectations]);

            return $vente->id;
        });
    }
}

<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Réservations de poussins (RES).
 * Compatibilité avec l'ancienne application : resa_lots_previsionnels.nombre_prevu porte le disponible restant à réserver.
 * EC14 : ce disponible est relu et décrémenté sous verrou, jamais reçu du navigateur.
 */
class Reservations
{
    public const STATUTS = [
        'pré-réservé' => ['Pré-réservé', 'attente'],
        'confirmé' => ['Confirmé', 'ok'],
        // La colonne statut est limitée à 20 caractères : « partiellement confirmé » (22) n'y tient pas.
        self::PARTIEL => ['Partiellement confirmé', 'info'],
        'livré' => ['Livré', 'neutre'],
        'annulé' => ['Annulé', 'neutre'],
    ];

    public const PARTIEL = 'confirmé partiel';

    /** Statut normalisé : les variantes historiques tronquées (« partiellement confir… ») sont reconnues. */
    public static function normalise(?string $statut): string
    {
        $s = trim((string) $statut);

        return str_starts_with(mb_strtolower($s), 'partiel') ? self::PARTIEL : $s;
    }

    public static function libelle(?string $statut): array
    {
        return self::STATUTS[self::normalise($statut)] ?? [$statut ?: '—', 'alerte'];
    }

    /** RG-RES-001 à 003 : réservation sur un lot prévisionnel non encore éclos. */
    public static function reserver(array $d): int
    {
        $quantite = (int) $d['quantite'];
        if ($quantite <= 0) {
            throw new RegleMetier('La quantité doit être strictement positive.');
        }
        $prix = Montant::saisie($d['prix_unitaire'], 'Le prix unitaire');
        $acompte = Montant::saisie($d['acompte'] ?? '0', 'L’acompte');
        // RG-RES-003 : prix et acompte stockés en entiers (FCFA).
        if ($prix % 100 !== 0 || $acompte % 100 !== 0) {
            throw new RegleMetier('Le prix et l’acompte d’une réservation s’expriment en FCFA entiers.');
        }
        if ($acompte > Montant::produit($quantite, $prix)) {
            throw new RegleMetier('L’acompte dépasse le montant de la réservation.');
        }

        return DB::transaction(function () use ($d, $quantite, $prix, $acompte) {
            $prev = DB::table('resa_lots_previsionnels')->where('id_lot', $d['id_lot_prevu'])->lockForUpdate()->first() ?? throw new RegleMetier('Lot prévisionnel introuvable.');
            $inc = DB::table('lots_incubation')->where('id', $prev->id_lot)->first();
            if ($inc && Incubation::estEclose($inc)) {
                throw new RegleMetier('Ce lot est déjà éclos : les réservations se traitent maintenant depuis la liste.');
            }
            $dispo = (int) $prev->nombre_prevu;
            if ($quantite > $dispo) {
                throw new RegleMetier("Quantité supérieure au disponible actuel ($dispo).");
            }
            $id = DB::table('resa_reservations')->insertGetId([
                'produit' => mb_substr((string) ($d['produit'] ?? 'poussin'), 0, 50), 'id_lot_prevu' => $prev->id_lot,
                'nom_client' => mb_substr(trim($d['nom_client']), 0, 100), 'telephone' => Ventes::telephone((string) $d['telephone']),
                'quantite' => $quantite, 'prix_unitaire' => intdiv($prix, 100), 'quantite_servie' => 0,
                'date_reservation' => today()->toDateString(), 'statut' => 'pré-réservé', 'commentaire' => $d['commentaire'] ?? null,
                'acompte' => intdiv($acompte, 100),
            ]);
            DB::table('resa_lots_previsionnels')->where('id_lot', $prev->id_lot)->update(['nombre_prevu' => $dispo - $quantite]);
            Journal::enregistrer('reservation.creation', 'RES', 'reservation', $id, ['disponible' => $dispo], ['disponible' => $dispo - $quantite, 'quantite' => $quantite]);

            return $id;
        });
    }

    /** Annulation d'une pré-réservation : la quantité revient au disponible. */
    public static function annuler(int $id, string $motif): void
    {
        if (mb_strlen(trim($motif)) < 3) {
            throw new RegleMetier('Indiquez le motif de l’annulation.');
        }
        DB::transaction(function () use ($id, $motif) {
            $r = DB::table('resa_reservations')->where('id_reservation', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Réservation introuvable.');
            if ($r->statut !== 'pré-réservé') {
                throw new RegleMetier('Seule une réservation pré-réservée peut être annulée ici.');
            }
            $prev = DB::table('resa_lots_previsionnels')->where('id_lot', $r->id_lot_prevu)->lockForUpdate()->first();
            DB::table('resa_reservations')->where('id_reservation', $id)->update([
                'statut' => 'annulé', 'commentaire' => trim(($r->commentaire ? $r->commentaire."\n" : '').'Annulée le '.now()->format('d/m/Y').' par '.Format::auteur().' : '.trim($motif)),
            ]);
            if ($prev) {
                DB::table('resa_lots_previsionnels')->where('id_lot', $r->id_lot_prevu)->update(['nombre_prevu' => (int) $prev->nombre_prevu + (int) $r->quantite]);
            }
            Journal::enregistrer('reservation.annulation', 'RES', 'reservation', $id, (array) $r, ['statut' => 'annulé'], trim($motif));
        });
    }

    /**
     * RG-RES-004 à 006 : après éclosion, sert les réservations par ordre chronologique.
     * Le stock est calculé par le serveur (poussins éclos − déjà servis) : relancer ne sert jamais deux fois.
     *
     * @return array{stock:int, servis:int, confirmees:int, partielles:int}
     */
    public static function traiter(int $idLotIncubation): array
    {
        return DB::transaction(function () use ($idLotIncubation) {
            $inc = DB::table('lots_incubation')->where('id', $idLotIncubation)->lockForUpdate()->first() ?? throw new RegleMetier('Lot introuvable.');
            if (! Incubation::estEclose($inc)) {
                throw new RegleMetier('Ce lot n’est pas encore éclos.');
            }
            $stockJour = DB::table('stock_poussins_jour')->where('id_lot', $idLotIncubation)->lockForUpdate()->first();
            $eclos = $stockJour ? (int) $stockJour->nombre : (int) $inc->nombre_eclos;
            $resas = DB::table('resa_reservations')->where('id_lot_prevu', $idLotIncubation)->whereNotIn('statut', ['annulé'])
                ->orderBy('date_reservation')->orderBy('id_reservation')->lockForUpdate()->get();
            $dejaServis = (int) $resas->sum('quantite_servie');
            $stock = max(0, $eclos - $dejaServis);
            $res = ['stock' => $stock, 'servis' => 0, 'confirmees' => 0, 'partielles' => 0];
            foreach ($resas as $r) {
                if (! in_array(self::normalise($r->statut), ['pré-réservé', self::PARTIEL], true)) {
                    continue;
                }
                if ($stock <= 0) {
                    break;
                }
                $manque = (int) $r->quantite - (int) $r->quantite_servie;
                $sert = min($manque, $stock);
                $statut = $sert === $manque ? 'confirmé' : self::PARTIEL;
                DB::table('resa_reservations')->where('id_reservation', $r->id_reservation)->update(['quantite_servie' => (int) $r->quantite_servie + $sert, 'statut' => $statut]);
                $stock -= $sert;
                $res['servis'] += $sert;
                $res[$statut === 'confirmé' ? 'confirmees' : 'partielles']++;
            }
            if ($stockJour) {
                DB::table('stock_poussins_jour')->where('id', $stockJour->id)->update(['vendus' => $dejaServis + $res['servis']]);
            }
            Journal::enregistrer('reservation.traitement', 'RES', 'incubation', $idLotIncubation, apres: $res);

            return $res;
        });
    }

    /**
     * RG-RES-007, 008 : livraison. Facultativement, crée la vente correspondante (sortie du lot de poussins, EC06)
     * et enregistre l'acompte déjà versé comme premier encaissement, pour qu'il ne soit ni oublié ni compté deux fois.
     */
    public static function livrer(int $id, array $d): ?int
    {
        return DB::transaction(function () use ($id, $d) {
            $r = DB::table('resa_reservations')->where('id_reservation', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Réservation introuvable.');
            if (! in_array(self::normalise($r->statut), ['confirmé', self::PARTIEL], true) || (int) $r->quantite_servie <= 0) {
                throw new RegleMetier('Seule une réservation confirmée (au moins en partie) peut être livrée.');
            }
            $date = Carbon::parse($d['date_livraison']);
            if ($date->isAfter(today())) {
                throw new RegleMetier('La date de livraison ne peut pas être future.');
            }
            $idVente = null;
            if (! empty($d['creer_vente'])) {
                $lot = DB::table('modLotVol_lots_volailles')->where('id_lot_incubation_source', $r->id_lot_prevu)->first();
                $idVente = Ventes::creer([
                    'date_vente' => $date->toDateString(), 'client_nom' => $r->nom_client, 'client_tel' => $r->telephone,
                    'produit' => $d['produit'], 'quantite' => (int) $r->quantite_servie, 'prix_unitaire' => (string) (int) $r->prix_unitaire,
                    'id_lot' => $lot && $lot->statut === 'actif' ? $lot->id : null, 'origine' => $d['origine'] ?? null,
                    'commentaire' => "Livraison de la réservation n° {$r->id_reservation}",
                ]);
                $acompte = (int) $r->acompte * 100;
                if (! empty($d['encaisser_acompte']) && $acompte > 0) {
                    Tresorerie::encaisser($idVente, [
                        'mode' => 'partiel', 'montant' => (string) min((int) $r->acompte, (int) $r->quantite_servie * (int) $r->prix_unitaire),
                        'date_op' => $date->toDateString(), 'moyen_paiement' => $d['moyen_paiement'] ?? 'Espèces',
                        'observations' => "Acompte versé à la réservation du ".Format::date($r->date_reservation),
                    ]);
                }
            }
            DB::table('resa_reservations')->where('id_reservation', $id)->update(['statut' => 'livré', 'date_livraison' => $date->toDateString()]);
            Journal::enregistrer('reservation.livraison', 'RES', 'reservation', $id, ['statut' => $r->statut], ['statut' => 'livré', 'vente' => $idVente]);

            return $idVente;
        });
    }
}

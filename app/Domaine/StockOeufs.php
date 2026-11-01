<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Stock des œufs validés et affectations À incuber / À vendre (OEU). */
class StockOeufs
{
    public const TYPES = ['incuber' => 'À incuber', 'vendre' => 'À vendre'];

    /**
     * RG-OEU-002 à 004 : contrôle de conservation d'un stock (lecture).
     * Œufs valides du ramassage = stock restant + somme de tous ses lots.
     *
     * @return array{ok:bool, message:?string, valides:?int, affectes:int}
     */
    public static function audit(object $stock): array
    {
        $ramassages = DB::table('suivi_ponte')->where('id_bande', $stock->id_bande)->where('date', $stock->date_ramassage)->get();
        $stocks = DB::table('stock_oeufs_ramasses')->where('id_bande', $stock->id_bande)->where('date_ramassage', $stock->date_ramassage)->count();
        $lots = DB::table('lots_oeufs_ramasses')->where('id_stock_oeufs', $stock->id)->get();
        $affectes = (int) $lots->sum('quantite');
        if ($ramassages->count() !== 1 || $stocks !== 1) {
            return ['ok' => false, 'message' => 'Il doit exister exactement un ramassage et un stock pour cette bande et cette date.', 'valides' => null, 'affectes' => $affectes];
        }
        $r = $ramassages->first();
        if (Ponte::etat($r) !== 'valide') {
            return ['ok' => false, 'message' => 'Le ramassage d’origine n’est pas validé ou ses quantités sont incohérentes.', 'valides' => null, 'affectes' => $affectes];
        }
        foreach ($lots as $lot) {
            if ((int) $lot->quantite <= 0 || (int) $lot->id_bande !== (int) $stock->id_bande) {
                return ['ok' => false, 'message' => "Le lot n° {$lot->id} a une quantité ou une bande incohérente.", 'valides' => (int) $r->total_oeufs_valides, 'affectes' => $affectes];
            }
        }
        if ((int) $r->total_oeufs_valides !== (int) $stock->total_oeufs + $affectes) {
            return ['ok' => false, 'message' => 'Conservation non respectée : œufs validés ≠ stock restant + lots affectés.', 'valides' => (int) $r->total_oeufs_valides, 'affectes' => $affectes];
        }

        return ['ok' => true, 'message' => null, 'valides' => (int) $r->total_oeufs_valides, 'affectes' => $affectes];
    }

    /** RG-OEU-001, 005, 006 : création d'un lot affecté et décrément du stock. */
    public static function affecter(int $idStock, string $type, int $quantite, string $empreinte): int
    {
        if (! array_key_exists($type, self::TYPES)) {
            throw new RegleMetier('Choisissez « À incuber » ou « À vendre ».');
        }
        if ($quantite <= 0) {
            throw new RegleMetier('La quantité doit être un entier strictement positif.');
        }

        return DB::transaction(function () use ($idStock, $type, $quantite, $empreinte) {
            $stock = DB::table('stock_oeufs_ramasses')->where('id', $idStock)->first() ?? throw new RegleMetier('Stock introuvable.');
            Reproducteurs::verrouiller((int) $stock->id_bande);
            $stock = DB::table('stock_oeufs_ramasses')->where('id', $idStock)->lockForUpdate()->first();
            \App\Support\Empreinte::verifier($stock, $empreinte, 'Ce stock a changé depuis l’ouverture de la page. Rechargez-la.');
            if ($stock->statut !== 'attente' || (int) $stock->total_oeufs <= 0) {
                throw new RegleMetier('Ce stock n’est plus disponible.');
            }
            if (! $stock->date_ramassage || Carbon::parse($stock->date_ramassage)->isAfter(today())) {
                throw new RegleMetier('Date du stock invalide ou future.');
            }
            $audit = self::audit($stock);
            if (! $audit['ok']) {
                throw new RegleMetier('Affectation bloquée : '.$audit['message']);
            }
            if ($quantite > (int) $stock->total_oeufs) {
                throw new RegleMetier("La quantité dépasse le stock disponible ({$stock->total_oeufs}).");
            }
            $id = DB::table('lots_oeufs_ramasses')->insertGetId([
                'date_creation' => today()->toDateString(),
                'id_stock_oeufs' => $stock->id,
                'id_bande' => $stock->id_bande,
                'type_lot' => $type,
                'quantite' => $quantite,
                'statut' => 'nouveau',
                'utilisateur' => Format::auteur(),
            ]);
            $reste = (int) $stock->total_oeufs - $quantite;
            DB::table('stock_oeufs_ramasses')->where('id', $stock->id)->update(['total_oeufs' => $reste, 'statut' => $reste > 0 ? 'attente' : 'affecte']);
            Journal::enregistrer('oeufs.affectation', 'OEU', 'stock_oeufs', $stock->id, ['restant' => (int) $stock->total_oeufs], ['restant' => $reste, 'lot' => $id, 'type' => $type, 'quantite' => $quantite]);

            return $id;
        });
    }
}

<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Journal;
use App\Support\Lieux;
use App\Support\Montant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rapprochements ventes / paiements (RAP). Corrige les liaisons et les soldes historiques
 * sans jamais créer de paiement : seules des recettes existantes sont rattachées.
 */
class Rapprochements
{
    public const DUREE_TICKET = 1200;

    public const TICKETS_MAX = 8;

    /** Recettes autonomes rattachables à une vente (RG-RAP-005, 006). */
    public static function candidates(object $vente): Collection
    {
        $borne = Exercices::borneCloture();

        return DB::table('modCompta_operations')->where('type_op', 'recette')->where('est_valide', 1)
            ->where(fn ($q) => $q->whereNull('source_table')->orWhere('source_table', ''))
            ->where('date_op', '>=', $vente->date_vente)->where('date_op', '<=', today()->toDateString())
            ->when($borne, fn ($q) => $q->where('date_op', '>', $borne))
            ->where(fn ($q) => $q->whereNull('date_vente')->orWhere('date_vente', $vente->date_vente))
            ->where(fn ($q) => $q->whereNull('id_lot')->orWhere('id_lot', $vente->id_lot ?? -1))
            ->orderBy('date_op')->orderBy('id')->limit(50)->get();
    }

    private static function etat(int $idVente, array $ids, bool $verrou): array
    {
        $vente = DB::table('modVente_Ventes')->where('id', $idVente)->when($verrou, fn ($q) => $q->lockForUpdate())->first() ?? throw new RegleMetier('Vente introuvable.');
        $liees = Tresorerie::encaisse($idVente, $verrou)['operations'];
        $nouvelles = DB::table('modCompta_operations')->whereIn('id', $ids ?: [0])->orderBy('id')->when($verrou, fn ($q) => $q->lockForUpdate())->get();

        return [$vente, $liees, $nouvelles];
    }

    /** RG-RAP-003 à 008 : calcul du plan de rapprochement (sans écriture). */
    public static function plan(int $idVente, array $ids, string $motif, bool $verrou = false): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) > 30) {
            throw new RegleMetier('30 opérations au maximum par rapprochement.');
        }
        if (strlen(trim($motif)) < 10 || mb_strlen($motif) > 2000) {
            throw new RegleMetier('Décrivez les justificatifs (10 à 2 000 caractères).');
        }
        [$vente, $liees, $nouvelles] = self::etat($idVente, $ids, $verrou);
        $borne = Exercices::borneCloture();
        if (! $vente->date_vente || Carbon::parse($vente->date_vente)->isAfter(today()) || ($borne && $vente->date_vente <= $borne)) {
            throw new RegleMetier('Cette vente est en période clôturée ou sa date est invalide : consultation seulement.');
        }
        $total = Montant::base($vente->montant_total);
        if (Montant::produit((int) $vente->quantite, Montant::base($vente->prix_unitaire)) !== $total) {
            throw new RegleMetier('Le total ne correspond pas à quantité × prix : le rapprochement ne corrige pas le prix ni la quantité.');
        }
        if ($liees->contains('type_op', 'depense')) {
            throw new RegleMetier('Une dépense est liée à cette vente : à examiner séparément.');
        }
        if ($nouvelles->count() !== count($ids)) {
            throw new RegleMetier('Une opération sélectionnée est introuvable.');
        }
        foreach ($nouvelles as $o) {
            if ($o->type_op !== 'recette' || (int) $o->est_valide !== 1 || trim((string) $o->source_table) !== '') {
                throw new RegleMetier("L’opération n° {$o->id} n’est pas une recette autonome valide.");
            }
            if ($o->date_op < $vente->date_vente || Carbon::parse($o->date_op)->isAfter(today()) || ($borne && $o->date_op <= $borne)) {
                throw new RegleMetier("La date de l’opération n° {$o->id} est incompatible (avant la vente, future ou clôturée).");
            }
            if (($o->date_vente && $o->date_vente !== $vente->date_vente) || ($o->id_lot && (int) $o->id_lot !== (int) $vente->id_lot)) {
                throw new RegleMetier("L’opération n° {$o->id} porte une autre date de vente ou un autre lot.");
            }
        }
        $cumul = $liees->sum(fn ($o) => Montant::base($o->montant)) + $nouvelles->sum(fn ($o) => Montant::base($o->montant));
        if ($cumul > $total) {
            throw new RegleMetier('Le cumul des recettes ('.Montant::fcfa($cumul).') dépasse le montant de la vente ('.Montant::fcfa($total).').');
        }
        $reste = $total - $cumul;
        $statut = $reste === 0 ? 'Encaissé' : 'A encaisser';
        if ($nouvelles->isEmpty() && Montant::base($vente->montant_reste_encaisser) === $reste && $vente->statut === $statut) {
            throw new RegleMetier('Cette vente est déjà cohérente : aucune correction à faire.');
        }

        return [
            'vente' => (int) $vente->id, 'ids' => $ids, 'motif' => trim($motif),
            'avant' => ['reste' => Montant::base($vente->montant_reste_encaisser), 'statut' => $vente->statut, 'liees' => $liees->pluck('id')->all()],
            'apres' => ['reste' => $reste, 'statut' => $statut, 'ajoutees' => $ids],
            'total' => $total, 'cumul' => $cumul,
            'empreinte' => Empreinte::de(['v' => $vente, 'l' => $liees->all(), 'n' => $nouvelles->all()]),
        ];
    }

    /** RG-RAP-009 : aperçu mémorisé en session (20 minutes, 8 au plus), attaché à son auteur. */
    public static function preparer(int $idVente, array $ids, string $motif): string
    {
        $plan = self::plan($idVente, $ids, $motif);
        $jeton = bin2hex(random_bytes(32));
        $tickets = collect(session('rapprochements', []))->filter(fn ($t) => $t['expire'] > time())->sortBy('expire')->take(-(self::TICKETS_MAX - 1));
        $tickets[$jeton] = $plan + ['auteur' => auth()->id(), 'expire' => time() + self::DUREE_TICKET];
        session(['rapprochements' => $tickets->all()]);

        return $jeton;
    }

    public static function ticket(string $jeton): array
    {
        $t = session('rapprochements', [])[$jeton] ?? null;
        if (! $t || $t['auteur'] !== auth()->id() || $t['expire'] < time()) {
            throw new RegleMetier('Aperçu expiré ou introuvable : préparez un nouvel aperçu.');
        }

        return $t;
    }

    /** RG-RAP-010 à 012 : confirmation, relecture sous verrou et jeton unique. */
    public static function confirmer(string $jeton, bool $confirmation, bool $historiqueComplet): void
    {
        if (! $confirmation || ! $historiqueComplet) {
            throw new RegleMetier('Cochez les deux attestations pour confirmer.');
        }
        $t = self::ticket($jeton);
        try {
            DB::transaction(function () use ($t, $jeton) {
                $plan = self::plan($t['vente'], $t['ids'], $t['motif'], true);
                if (! hash_equals($t['empreinte'], $plan['empreinte'])) {
                    throw new RegleMetier('La vente ou les opérations ont changé depuis l’aperçu. Préparez un nouvel aperçu.');
                }
                $vente = DB::table('modVente_Ventes')->where('id', $t['vente'])->first();
                foreach ($t['ids'] as $id) {
                    Lieux::executer(fn () => DB::table('modCompta_operations')->where('id', $id)->update([
                        'source_table' => Tresorerie::SOURCE_VENTE, 'source_id' => $vente->id, 'date_vente' => $vente->date_vente, 'id_lot' => $vente->id_lot,
                    ]));
                }
                Lieux::executer(fn () => DB::table('modVente_Ventes')->where('id', $vente->id)->update([
                    'montant_reste_encaisser' => Montant::versBase($plan['apres']['reste']), 'statut' => $plan['apres']['statut'],
                ]));
                $controle = Tresorerie::audit(DB::table('modVente_Ventes')->where('id', $vente->id)->first(), true);
                if (! $controle['ok']) {
                    throw new RegleMetier('Contrôle final incohérent : '.$controle['message']);
                }
                $user = auth()->user();
                DB::table('modCompta_rapprochements')->insert([
                    'request_token' => $jeton, 'id_vente' => $vente->id, 'created_at' => now(), 'author_id' => $user->id,
                    'author_name' => mb_substr($user->nomComplet(), 0, 150), 'motif' => $t['motif'],
                    'avant_json' => json_encode($plan['avant'] + ['vente' => (array) $vente]), 'apres_json' => json_encode($plan['apres']),
                ]);
                Journal::enregistrer('rapprochement.confirmation', 'RAP', 'vente', $vente->id, $plan['avant'], $plan['apres'], $t['motif']);
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new RegleMetier('Ce rapprochement a déjà été validé.');
            }
            throw $e;
        }
        $tickets = session('rapprochements', []);
        unset($tickets[$jeton]);
        session(['rapprochements' => $tickets]);
    }

    /** Ventes de l'année avec leur écart éventuel (RG-RAP-002). */
    public static function ventes(int $annee, ?string $recherche, bool $ecartSeulement)
    {
        $paye = DB::table('modCompta_operations as o')->whereRaw("LOWER(TRIM(o.source_table)) = 'modvente_ventes'")->where('o.est_valide', 1)->groupBy('o.source_id')
            ->select('o.source_id', DB::raw("SUM(CASE WHEN o.type_op='recette' THEN o.montant ELSE 0 END) p"), DB::raw("SUM(o.type_op='depense') d"));

        return DB::table('modVente_Ventes as v')->leftJoinSub($paye, 'x', 'x.source_id', '=', 'v.id')
            ->whereYear('v.date_vente', $annee)
            ->when($recherche, fn ($q, $r) => $q->where(fn ($w) => $w->where('v.client_nom', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $r).'%')->orWhere('v.client_tel', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $r).'%')))
            ->when($ecartSeulement, fn ($q) => $q->whereRaw('(v.montant_reste_encaisser <> v.montant_total - COALESCE(x.p,0) OR COALESCE(x.d,0) > 0)'))
            ->orderByDesc('v.date_vente')->orderByDesc('v.id')
            ->select('v.*', DB::raw('COALESCE(x.p,0) paye'), DB::raw('COALESCE(x.d,0) depenses'))->paginate(30);
    }
}

<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Journal;
use App\Support\Lieux;
use App\Support\Montant;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bilan annuel et clôture des exercices (EXE).
 * La clôture produit une archive définitive (JSON + PDF + empreintes SHA-256) que les déclencheurs rendent immuable.
 */
class Exercices
{
    public const LIGNES_MAX = 20000;

    public const JSON_MAX = 8_000_000;

    private const VENTE = "LOWER(TRIM(o.source_table)) = 'modvente_ventes'";

    public static function bornes(int $annee): array
    {
        if ($annee < 1900 || $annee > 2099) {
            throw new RegleMetier('Année invalide.');
        }
        $courante = (int) today()->year;
        if ($annee > $courante) {
            throw new RegleMetier('Aucun bilan pour une année future.');
        }

        return ['debut' => "$annee-01-01", 'arrete' => $annee === $courante ? today()->toDateString() : "$annee-12-31", 'provisoire' => $annee === $courante];
    }

    public static function borneCloture(): ?string
    {
        return DB::table('modCompta_exercice_lock')->where('id', 1)->value('closed_through');
    }

    /** RG-EXE-001 à 004 : bilan d'une année (centimes). */
    public static function bilan(int $annee): array
    {
        ['debut' => $debut, 'arrete' => $arrete, 'provisoire' => $provisoire] = self::bornes($annee);

        $ventes = DB::table('modVente_Ventes')->whereBetween('date_vente', [$debut, $arrete])->groupBy('espece')
            ->select('espece', DB::raw('COUNT(*) n'), DB::raw('SUM(quantite) q'), DB::raw('SUM(montant_total) total'))->orderByDesc(DB::raw('SUM(montant_total)'))->get()
            ->map(fn ($v) => ['produit' => $v->espece, 'libelle' => Ventes::libelle($v->espece), 'n' => (int) $v->n, 'quantite' => (int) $v->q, 'total' => Montant::base($v->total)]);

        $ops = DB::table('modCompta_operations')->where('est_valide', 1)->whereBetween('date_op', [$debut, $arrete])->groupBy('type_op', 'categorie')
            ->select('type_op', 'categorie', DB::raw('SUM(montant) total'), DB::raw('COUNT(*) n'))->orderBy('type_op')->orderByDesc(DB::raw('SUM(montant)'))->get()
            ->map(fn ($o) => ['type' => $o->type_op, 'categorie' => $o->categorie, 'total' => Montant::base($o->total), 'n' => (int) $o->n]);
        $recettes = $ops->where('type', 'recette')->sum('total');
        $depenses = $ops->where('type', 'depense')->sum('total');

        // RG-EXE-003 : créances reconstruites à l'arrêté (et non reprises du reste courant).
        $creances = DB::table('modVente_Ventes as v')
            ->leftJoinSub(DB::table('modCompta_operations as o')->whereRaw(self::VENTE)->where('o.est_valide', 1)->where('o.date_op', '<=', $arrete)
                ->groupBy('o.source_id')->select('o.source_id', DB::raw("SUM(CASE WHEN o.type_op='recette' THEN o.montant ELSE -o.montant END) paye")), 'p', 'p.source_id', '=', 'v.id')
            ->where('v.date_vente', '<=', $arrete)->whereRaw('v.montant_total - COALESCE(p.paye,0) <> 0')
            ->orderBy('v.date_vente')->get(['v.id', 'v.date_vente', 'v.client_nom', 'v.espece', 'v.montant_total', DB::raw('v.montant_total - COALESCE(p.paye,0) creance')])
            ->map(fn ($c) => ['id' => (int) $c->id, 'date' => $c->date_vente, 'client' => $c->client_nom, 'produit' => Ventes::libelle($c->espece), 'total' => Montant::base($c->montant_total), 'creance' => Montant::base($c->creance)]);

        // RG-EXE-004 : recettes de l'année encaissées pour des ventes antérieures.
        $anterieures = DB::table('modCompta_operations as o')->join('modVente_Ventes as v', 'v.id', '=', 'o.source_id')->whereRaw(self::VENTE)
            ->where('o.est_valide', 1)->where('o.type_op', 'recette')->whereBetween('o.date_op', [$debut, $arrete])->where('v.date_vente', '<', $debut)
            ->select(DB::raw('COUNT(*) n'), DB::raw('COALESCE(SUM(o.montant),0) total'))->first();

        $annexe = DB::table('commandes_vente')->whereBetween('date_reservation', [$debut, $arrete])
            ->select(DB::raw('COUNT(*) n'), DB::raw('COALESCE(SUM(prix_total),0) total'))->first();

        return [
            'annee' => $annee, 'debut' => $debut, 'arrete' => $arrete, 'provisoire' => $provisoire,
            'ventes' => $ventes->values()->all(), 'ventes_total' => $ventes->sum('total'), 'ventes_nombre' => $ventes->sum('n'),
            'operations' => $ops->values()->all(), 'recettes' => $recettes, 'depenses' => $depenses, 'mouvement_net' => $recettes - $depenses,
            'creances' => $creances->values()->all(), 'creances_total' => $creances->sum('creance'),
            'recettes_ventes_anterieures' => ['n' => (int) $anterieures->n, 'total' => Montant::base($anterieures->total)],
            'annexe_commandes' => ['n' => (int) $annexe->n, 'total' => Montant::base($annexe->total)],
        ];
    }

    /**
     * RG-EXE-007, 008 : contrôles avant clôture jusqu'à $cutoff.
     * Une vérification qui échoue est un blocage (jamais « zéro anomalie »).
     */
    public static function controles(string $cutoff, int $annee): array
    {
        $paye = DB::table('modCompta_operations as o')->whereRaw(self::VENTE)->where('o.est_valide', 1)->groupBy('o.source_id')
            ->select('o.source_id', DB::raw("SUM(CASE WHEN o.type_op='recette' THEN o.montant ELSE -o.montant END) p"));
        $verifs = [
            'soldes' => ['Soldes différents des règlements liés', fn () => DB::table('modVente_Ventes as v')->leftJoinSub($paye, 'x', 'x.source_id', '=', 'v.id')
                ->where('v.date_vente', '<=', $cutoff)->whereRaw('v.montant_reste_encaisser <> v.montant_total - COALESCE(x.p,0)')->pluck('v.id')],
            'orphelines' => ['Opérations liées à une vente inexistante', fn () => DB::table('modCompta_operations as o')->whereRaw(self::VENTE)
                ->leftJoin('modVente_Ventes as v', 'v.id', '=', 'o.source_id')->whereNull('v.id')->where('o.date_op', '<=', $cutoff)->pluck('o.id')],
            'paiements_anterieurs' => ['Paiements antérieurs à la vente', fn () => DB::table('modCompta_operations as o')->join('modVente_Ventes as v', 'v.id', '=', 'o.source_id')
                ->whereRaw(self::VENTE)->where('o.est_valide', 1)->whereColumn('o.date_op', '<', 'v.date_vente')->where('v.date_vente', '<=', $cutoff)->pluck('o.id')],
            'depenses_liees' => ['Dépenses liées à des ventes', fn () => DB::table('modCompta_operations as o')->whereRaw(self::VENTE)->where('o.est_valide', 1)
                ->where('o.type_op', 'depense')->where('o.date_op', '<=', $cutoff)->pluck('o.id')],
            'dates' => ['Dates invalides', fn () => DB::table('modVente_Ventes')->where(fn ($q) => $q->whereNull('date_vente')->orWhere('date_vente', '<=', '0000-00-00'))->pluck('id')
                ->merge(DB::table('modCompta_operations')->where(fn ($q) => $q->whereNull('date_op')->orWhere('date_op', '<=', '0000-00-00'))->pluck('id')->map(fn ($i) => 'op'.$i))],
            'montants' => ['Montants incohérents (total, reste)', fn () => DB::table('modVente_Ventes')->where('date_vente', '<=', $cutoff)
                ->whereRaw('(ROUND(quantite * prix_unitaire, 2) <> montant_total OR montant_reste_encaisser < 0 OR montant_reste_encaisser > montant_total)')->pluck('id')],
            'a_completer' => ['Sorties de volailles à compléter', fn () => DB::table('modVente_Ventes_A_Completer')->whereRaw("LOWER(TRIM(statut)) = 'attente'")
                ->where('date_vente', '<=', $cutoff)->pluck('id')],
            'ovins' => ['Ventes ovines non rapprochées d’une sortie', fn () => DB::table('modVente_Ventes as v')->leftJoin('modVente_ovins_liaisons as l', 'l.id_vente', '=', 'v.id')
                ->where('v.espece', 'ovin')->where('v.date_vente', '<=', $cutoff)->whereNull('l.id')->pluck('v.id')],
            'oeufs_ventes' => ['Ventes d’œufs dont les affectations ne correspondent pas à la quantité', fn () => DB::table('modVente_Ventes as v')
                ->leftJoinSub(DB::table('modVente_oeufs_affectations')->groupBy('id_vente')->select('id_vente', DB::raw('SUM(quantite) q')), 'a', 'a.id_vente', '=', 'v.id')
                ->where('v.espece', 'like', 'oeufs%')->where('v.date_vente', '<=', $cutoff)->whereNotNull('a.q')->whereColumn('a.q', '<>', 'v.quantite')->pluck('v.id')],
            'oeufs_lots' => ['Lots d’œufs vendus au-delà de leur quantité', fn () => DB::table('lots_oeufs_ramasses as l')
                ->joinSub(DB::table('modVente_oeufs_affectations')->groupBy('id_lot_oeufs')->select('id_lot_oeufs', DB::raw('SUM(quantite) q')), 'a', 'a.id_lot_oeufs', '=', 'l.id')
                ->whereColumn('a.q', '>', 'l.quantite')->pluck('l.id')],
        ];
        $examens = [
            'autonomes' => ['Recettes non liées à une vente', fn () => DB::table('modCompta_operations')->where('type_op', 'recette')->where('est_valide', 1)
                ->where(fn ($q) => $q->whereNull('source_table')->orWhere('source_table', ''))->whereYear('date_op', $annee)->pluck('id')],
            'invalidees' => ['Opérations invalidées', fn () => DB::table('modCompta_operations')->where('est_valide', 0)->whereYear('date_op', $annee)->pluck('id')],
        ];
        $executer = function (array $liste) {
            $out = [];
            foreach ($liste as $cle => [$libelle, $requete]) {
                try {
                    $ids = $requete()->values();
                    $out[$cle] = ['libelle' => $libelle, 'nombre' => $ids->count(), 'ids' => $ids->take(self::LIGNES_MAX + 1)->all(), 'disponible' => true];
                } catch (\Throwable $e) {
                    report($e);
                    $out[$cle] = ['libelle' => $libelle, 'nombre' => null, 'ids' => [], 'disponible' => false];
                }
            }

            return $out;
        };
        $bloquants = $executer($verifs);
        $aExaminer = $executer($examens);

        return [
            'bloquants' => $bloquants,
            'a_examiner' => $aExaminer,
            'bloque' => collect($bloquants)->contains(fn ($c) => ! $c['disponible'] || $c['nombre'] > 0),
        ];
    }

    /** RG-EXE-006 : environnement nécessaire à une clôture. */
    public static function environnement(): array
    {
        $base = DB::getDatabaseName();
        $erreurs = [];
        $tables = ['modVente_Ventes', 'modCompta_operations', 'modCompta_exercices', 'modCompta_exercice_lock', 'modVente_oeufs_affectations', 'modVente_Ventes_A_Completer', 'modLotVol_mouvements'];
        $moteurs = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', $base)->whereIn('TABLE_NAME', $tables)->pluck('ENGINE', 'TABLE_NAME');
        foreach ($tables as $t) {
            if (strtoupper((string) ($moteurs[$t] ?? '')) !== 'INNODB') {
                $erreurs[] = "Table $t absente ou hors InnoDB.";
            }
        }
        $attendus = ['sf116_t0_bi', 'sf116_t0_bu', 'sf116_t0_bd', 'sf116_t1_bi', 'sf116_t1_bu', 'sf116_t1_bd', 'sf116_archive_bi', 'sf116_archive_bu', 'sf116_archive_ai', 'sf116_lock_bu'];
        $presents = DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $base)->whereIn('TRIGGER_NAME', $attendus)->pluck('TRIGGER_NAME')->all();
        foreach (array_diff($attendus, $presents) as $m) {
            $erreurs[] = "Déclencheur de protection $m absent.";
        }
        if (! DB::table('modCompta_exercice_lock')->where('id', 1)->exists()) {
            $erreurs[] = 'Verrou de clôture absent (modCompta_exercice_lock).';
        }
        if (! class_exists(Dompdf::class)) {
            $erreurs[] = 'Génération PDF indisponible.';
        }

        return $erreurs;
    }

    /** Rapport complet servant à l'aperçu et à l'archive. */
    public static function rapport(int $annee): array
    {
        $cutoff = "$annee-12-31";

        return ['bilan' => self::bilan($annee), 'controles' => self::controles($cutoff, $annee), 'cutoff' => $cutoff];
    }

    /** Vérifie qu'une année peut être clôturée (hors contrôles de données). */
    public static function verifierClotureAutorisee(int $annee): void
    {
        if (! auth()->user()?->estAdmin()) {
            throw new RegleMetier('La clôture est réservée à l’administrateur.');
        }
        if ($annee >= (int) gmdate('Y')) {
            throw new RegleMetier('Seule une année terminée peut être clôturée.');
        }
        $borne = self::borneCloture();
        if ($borne && "$annee-12-31" <= $borne) {
            throw new RegleMetier('Cette année est déjà protégée par une clôture.');
        }
        if ($erreurs = self::environnement()) {
            throw new RegleMetier('Clôture impossible : '.implode(' ', $erreurs));
        }
    }

    /** RG-EXE-009 : aperçu, avec révision et empreinte du rapport. */
    public static function apercu(int $annee): array
    {
        self::verifierClotureAutorisee($annee);
        $rapport = self::rapport($annee);
        $revision = (string) DB::table('modCompta_exercice_lock')->where('id', 1)->value('revision');

        return ['rapport' => $rapport, 'revision' => $revision, 'empreinte' => hash('sha256', json_encode($rapport))];
    }

    /** RG-EXE-005, 009 à 011 : clôture définitive. */
    public static function cloturer(int $annee, string $commentaire, string $revisionApercu, string $empreinteApercu): void
    {
        $octets = strlen(trim($commentaire));
        if ($octets < 10 || $octets > 4000) {
            throw new RegleMetier('Le commentaire de clôture doit compter entre 10 et 4 000 caractères.');
        }
        self::verifierClotureAutorisee($annee);

        DB::transaction(function () use ($annee, $commentaire, $revisionApercu, $empreinteApercu) {
            $verrou = DB::table('modCompta_exercice_lock')->where('id', 1)->lockForUpdate()->first();
            $rapport = self::rapport($annee);
            if ((string) $verrou->revision !== $revisionApercu || ! hash_equals(hash('sha256', json_encode($rapport)), $empreinteApercu)) {
                throw new RegleMetier('Des écritures ont changé depuis l’aperçu. Préparez un nouvel aperçu avant de clôturer.');
            }
            if ($rapport['controles']['bloque']) {
                throw new RegleMetier('Des anomalies bloquantes subsistent : la clôture est refusée.');
            }
            foreach ($rapport['controles']['bloquants'] + $rapport['controles']['a_examiner'] as $c) {
                if (($c['nombre'] ?? 0) > self::LIGNES_MAX) {
                    throw new RegleMetier('Une annexe dépasse 20 000 lignes : la clôture est refusée jusqu’à adaptation.');
                }
            }
            if (count($rapport['bilan']['creances']) > self::LIGNES_MAX) {
                throw new RegleMetier('L’annexe des créances dépasse 20 000 lignes : la clôture est refusée jusqu’à adaptation.');
            }
            $user = auth()->user();
            $meta = [
                'annee' => $annee, 'cutoff' => $rapport['cutoff'], 'clos_le' => now()->toIso8601String(), 'auteur' => $user->nomComplet(),
                'auteur_id' => $user->id, 'commentaire' => trim($commentaire), 'version' => config('somadi.version'), 'revision' => $revisionApercu,
            ];
            $json = json_encode(['meta' => $meta] + $rapport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > self::JSON_MAX) {
                throw new RegleMetier('L’archive dépasse 8 Mo : la clôture est refusée jusqu’à adaptation.');
            }
            $pdf = self::pdf($rapport, $meta);
            $paquet = (int) DB::selectOne('SELECT @@max_allowed_packet m')->m;
            if ($paquet < strlen($json) + strlen($pdf) + 1_000_000) {
                throw new RegleMetier('La base n’accepte pas une archive de cette taille (max_allowed_packet).');
            }
            Lieux::executer(fn () => DB::table('modCompta_exercices')->insert([
                'annee' => $annee, 'cutoff' => $rapport['cutoff'], 'closed_at' => now(), 'author_id' => $user->id,
                'author_name' => mb_substr($user->nomComplet(), 0, 150), 'commentaire' => trim($commentaire),
                'snapshot_json' => $json, 'snapshot_sha256' => hash('sha256', $json),
                'pdf_blob' => $pdf, 'pdf_sha256' => hash('sha256', $pdf), 'version_application' => mb_substr((string) config('somadi.version'), 0, 30),
            ]));
            Journal::enregistrer('exercice.cloture', 'EXE', 'exercice', $annee, ['borne' => $verrou->closed_through], ['borne' => $rapport['cutoff']], trim($commentaire));
        });
    }

    public static function pdf(array $rapport, array $meta): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('tresorerie.exercices.pdf', ['r' => $rapport, 'meta' => $meta])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** RG-EXE-012 : lecture d'une archive avec vérification des empreintes. */
    public static function archive(int $annee): array
    {
        $a = DB::table('modCompta_exercices')->where('annee', $annee)->first() ?? throw new RegleMetier('Aucune archive pour cette année.');
        $integre = hash_equals((string) $a->snapshot_sha256, hash('sha256', (string) $a->snapshot_json))
            && hash_equals((string) $a->pdf_sha256, hash('sha256', (string) $a->pdf_blob));

        return ['ligne' => $a, 'integre' => $integre, 'contenu' => $integre ? json_decode($a->snapshot_json, true) : null];
    }
}

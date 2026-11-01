<?php

namespace App\Domaine\Organisation;

use App\Exceptions\RegleMetier;
use App\Models\User;
use App\Support\Empreinte;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Activités : tâches, affectations, actions, routines et équipes (ACT, RG-ACT-001 à 018). */
class Activites
{
    public const PRIORITES = ['basse' => ['Basse', 'neutre'], 'normale' => ['Normale', 'info'], 'haute' => ['Haute', 'attente'], 'critique' => ['Critique', 'alerte']];

    public const STATUTS = ['planifie' => ['Planifiée', 'info'], 'en_cours' => ['En cours', 'attente'], 'realise' => ['Réalisée', 'ok'], 'annule' => ['Annulée', 'neutre'], 'reporte' => ['Reportée', 'attente']];

    public const ROLES = ['responsable' => 'Responsable', 'executeur' => 'Exécutant', 'observateur' => 'Observateur'];

    public const OCCUPATIONS = ['reunion' => 'Réunion', 'absence' => 'Absence', 'mission' => 'Mission', 'autre' => 'Autre'];

    // ----- Habilitations (RG-ACT-002, 004 à 007)

    public static function planificateur(?User $u = null): bool
    {
        $u ??= Auth::user();

        return $u && in_array($u->roleCanonique(), ['admin', 'gestionnaire', 'technicien'], true) && $u->can('ACT.gerer');
    }

    public static function gestionnaire(?User $u = null): bool
    {
        $u ??= Auth::user();

        return $u && in_array($u->roleCanonique(), ['admin', 'gestionnaire'], true) && $u->can('ACT.gerer');
    }

    /** Rôle d'affectation effectif de l'utilisateur sur la tâche (direct ou par équipe), le plus fort l'emporte. */
    public static function role(int $tache, ?int $user = null): ?string
    {
        $user ??= Auth::id();
        $equipes = DB::table('modAct_equipe_membres as m')->join('modAct_equipes as e', 'e.id', '=', 'm.id_equipe')->where('m.id_user', $user)->where('e.actif', 1)->pluck('m.id_equipe')->all();
        $roles = DB::table('modAct_tache_affectations')->where('id_tache', $tache)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('type_cible', 'user')->where('id_user', $user))->orWhere(fn ($w) => $w->where('type_cible', 'equipe')->whereIn('id_equipe', $equipes ?: [0])))
            ->pluck('role_affectation')->all();
        foreach (['responsable', 'executeur', 'observateur'] as $r) {
            if (in_array($r, $roles, true)) {
                return $r;
            }
        }

        return null;
    }

    /** Requête des tâches visibles : tout pour un planificateur, sinon celles où l'on est affecté (REC-ACT-001). */
    public static function visibles(?User $u = null)
    {
        $u ??= Auth::user();
        $q = DB::table('modAct_taches as t')->leftJoin('modAct_categories as c', 'c.id', '=', 't.id_categorie')
            ->leftJoin('modAct_projets as p', 'p.id', '=', 't.id_projet')->leftJoin('modAct_routines as r', 'r.id', '=', 't.id_routine')
            ->select('t.*', 'c.libelle as categorie', 'c.couleur', 'p.titre as projet', 'r.titre as routine');
        if (! self::planificateur($u) && ! $u->can('ACT.cloturer')) {
            $equipes = DB::table('modAct_equipe_membres')->where('id_user', $u->id)->pluck('id_equipe')->all();
            $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('modAct_tache_affectations as a')->whereColumn('a.id_tache', 't.id')
                ->where(fn ($w) => $w->where(fn ($x) => $x->where('a.type_cible', 'user')->where('a.id_user', $u->id))->orWhere(fn ($x) => $x->where('a.type_cible', 'equipe')->whereIn('a.id_equipe', $equipes ?: [0]))));
        }

        return $q;
    }

    public static function tache(int $id): object
    {
        $t = self::visibles()->where('t.id', $id)->first();
        if (! $t) {
            throw new RegleMetier('Tâche introuvable ou hors de votre périmètre.');
        }

        return $t;
    }

    public static function affectations(int $tache)
    {
        return DB::table('modAct_tache_affectations as a')->leftJoin('utilisateurs as u', 'u.id', '=', 'a.id_user')->leftJoin('modAct_equipes as e', 'e.id', '=', 'a.id_equipe')
            ->where('a.id_tache', $tache)->orderByRaw("FIELD(a.role_affectation, 'responsable', 'executeur', 'observateur')")
            ->get(['a.*', DB::raw("TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) as nom_user"), 'e.nom as nom_equipe', 'e.couleur as couleur_equipe']);
    }

    /** Empreinte de la tâche, de ses affectations et de ses pointages (RG-ACT-018). */
    public static function empreinte(int $id): string
    {
        return Empreinte::de(['t' => DB::table('modAct_taches')->where('id', $id)->first(),
            'a' => DB::table('modAct_tache_affectations')->where('id_tache', $id)->orderBy('id')->get()->all(),
            'p' => DB::table('modAct_tache_pointages')->where('id_tache', $id)->max('id')]);
    }

    // ----- Création et modification (RG-ACT-001 à 003, 009)

    private static function valeurs(array $d): array
    {
        $nom = trim((string) ($d['nom'] ?? ''));
        if ($nom === '' || mb_strlen($nom) > 200) {
            throw new RegleMetier('Le titre est obligatoire (200 caractères au plus).');
        }
        if (mb_strlen((string) ($d['description'] ?? '')) > 10000) {
            throw new RegleMetier('Description trop longue (10 000 caractères au plus).');
        }
        if (! isset(self::PRIORITES[$d['priorite'] ?? 'normale'])) {
            throw new RegleMetier('Priorité invalide.');
        }
        $dt = fn ($k) => ($d[$k] ?? '') !== '' ? Carbon::parse(str_replace('T', ' ', $d[$k]))->format('Y-m-d H:i:s') : null;
        [$deb, $fin, $ech] = [$dt('date_debut_prevue'), $dt('date_fin_prevue'), $dt('date_echeance')];
        if ($fin && ! $deb) {
            throw new RegleMetier('Une fin prévue demande un début.');
        }
        if ($fin && $fin < $deb) {
            throw new RegleMetier('La fin prévue ne précède pas le début.');
        }
        if ($ech && $deb && $ech < $deb) {
            throw new RegleMetier('L’échéance ne précède pas le début.');
        }
        if (! empty($d['id_categorie']) && ! DB::table('modAct_categories')->where('id', $d['id_categorie'])->where('actif', 1)->exists()) {
            throw new RegleMetier('Catégorie introuvable ou inactive.');
        }
        if (! empty($d['id_projet']) && ! DB::table('modAct_projets')->where('id', $d['id_projet'])->exists()) {
            throw new RegleMetier('Projet introuvable.');
        }
        $duree = ($d['duree_estimee_min'] ?? '') !== '' ? (int) $d['duree_estimee_min'] : ($deb && $fin ? (int) Carbon::parse($deb)->diffInMinutes(Carbon::parse($fin)) : null);

        return ['nom' => $nom, 'description' => ($d['description'] ?? null) ?: null, 'id_categorie' => ($d['id_categorie'] ?? null) ?: null, 'priorite' => $d['priorite'] ?? 'normale',
            'date_debut_prevue' => $deb, 'date_fin_prevue' => $fin, 'date_echeance' => $ech, 'duree_estimee_min' => $duree,
            'charge_jh' => ($d['charge_jh'] ?? '') !== '' ? round((float) str_replace(',', '.', $d['charge_jh']), 2) : null];
    }

    /** @return list<array{type_cible:string,id_user:?int,id_equipe:?int,role_affectation:string}> */
    private static function cibles(array $d): array
    {
        $out = [];
        $ajouter = function (string $type, int $id, string $role) use (&$out) {
            $k = $type.$id;
            if ($type === 'user' && ! DB::table('utilisateurs')->where('id', $id)->where('actif', 1)->exists()) {
                throw new RegleMetier('Utilisateur affecté introuvable ou inactif.');
            }
            if ($type === 'equipe' && ! DB::table('modAct_equipes')->where('id', $id)->where('actif', 1)->exists()) {
                throw new RegleMetier('Équipe affectée introuvable ou inactive.');
            }
            $out[$k] ??= ['type_cible' => $type, 'id_user' => $type === 'user' ? $id : null, 'id_equipe' => $type === 'equipe' ? $id : null, 'role_affectation' => $role];
        };
        if (! empty($d['responsable'])) {
            $ajouter('user', (int) $d['responsable'], 'responsable');
        }
        foreach ((array) ($d['executeurs'] ?? []) as $u) {
            $ajouter('user', (int) $u, 'executeur');
        }
        foreach ((array) ($d['equipes'] ?? []) as $e) {
            $ajouter('equipe', (int) $e, 'executeur');
        }
        foreach ((array) ($d['observateurs'] ?? []) as $u) {
            $ajouter('user', (int) $u, 'observateur');
        }
        if (! $out) {
            throw new RegleMetier('Affectez au moins une personne ou une équipe.');
        }

        return array_values($out);
    }

    private static function journal(int $tache, string $action, string $motif, mixed $avant, mixed $apres, string $cle): void
    {
        try {
            DB::table('modAct_journal_v112')->insert(['id_tache' => $tache, 'id_user' => Auth::id(), 'action' => $action, 'motif' => $motif,
                'avant_json' => $avant === null ? null : json_encode($avant, JSON_UNESCAPED_UNICODE), 'apres_json' => json_encode($apres, JSON_UNESCAPED_UNICODE), 'request_key' => $cle]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new RegleMetier('Cette action a déjà été enregistrée (double envoi).');
            }
            throw $e;
        }
        Journal::enregistrer('tache.'.$action, 'ACT', 'tache', $tache, $avant, $apres, $motif ?: null);
    }

    private static function pointage(int $tache, string $action, ?string $commentaire = null, ?int $duree = null): void
    {
        DB::table('modAct_tache_pointages')->insert(['id_tache' => $tache, 'id_user' => Auth::id(), 'action' => $action, 'commentaire' => $commentaire,
            'duree_delta_min' => $duree, 'ip' => request()?->ip()]);
    }

    public static function creer(array $d, string $cle): int
    {
        if (! self::planificateur()) {
            throw new RegleMetier('Seuls l’administrateur, le gestionnaire et le technicien planifient les tâches.');
        }
        $v = self::valeurs($d);
        $cibles = self::cibles($d);

        return DB::transaction(function () use ($v, $cibles, $d, $cle) {
            $id = DB::table('modAct_taches')->insertGetId($v + ['source_type' => 'projet', 'id_projet' => ($d['id_projet'] ?? null) ?: null, 'statut' => 'planifie', 'cree_par' => Auth::id()]);
            DB::table('modAct_tache_affectations')->insert(array_map(fn ($c) => $c + ['id_tache' => $id], $cibles));
            self::journal($id, 'creer', '', null, $v + ['affectations' => $cibles], $cle);

            return $id;
        });
    }

    public static function modifier(int $id, array $d, string $empreinte, string $cle): void
    {
        if (! self::planificateur()) {
            throw new RegleMetier('Modification réservée aux responsables de planning.');
        }
        DB::transaction(function () use ($id, $d, $empreinte, $cle) {
            $t = DB::table('modAct_taches')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Tâche introuvable.');
            if (! hash_equals(self::empreinte($id), $empreinte)) {
                throw new RegleMetier('La tâche a changé depuis l’ouverture de la page : rechargez-la.');
            }
            if (in_array($t->statut, ['realise', 'annule'], true)) {
                throw new RegleMetier('Une tâche réalisée ou annulée ne se modifie plus ; elle reste commentable.');
            }
            $v = self::valeurs($d);
            if ($t->source_type === 'projet') {
                $v['id_projet'] = ($d['id_projet'] ?? null) ?: null;
            }
            $cibles = self::cibles($d);
            $anciennes = DB::table('modAct_tache_affectations')->where('id_tache', $id)->get()->keyBy(fn ($a) => $a->type_cible.($a->id_user ?? $a->id_equipe));
            // RG-ACT-009 : les cibles déjà présentes gardent leur rôle, sauf choix explicite du responsable.
            foreach ($cibles as &$c) {
                $k = $c['type_cible'].($c['id_user'] ?? $c['id_equipe']);
                if (isset($anciennes[$k]) && $c['role_affectation'] === 'executeur') {
                    $c['role_affectation'] = $anciennes[$k]->role_affectation;
                }
            }
            unset($c);
            DB::table('modAct_taches')->where('id', $id)->update($v + ['maj_le' => now()]);
            DB::table('modAct_tache_affectations')->where('id_tache', $id)->delete();
            DB::table('modAct_tache_affectations')->insert(array_map(fn ($c) => $c + ['id_tache' => $id], $cibles));
            self::journal($id, 'modifier', '', (array) $t + ['affectations' => $anciennes->values()->all()], $v + ['affectations' => $cibles], $cle);
        });
    }

    /**
     * Actions sur une tâche : demarrer, terminer, annuler, reporter, commenter (RG-ACT-006 à 008).
     */
    public static function agir(int $id, string $action, array $d, string $empreinte, string $cle): void
    {
        DB::transaction(function () use ($id, $action, $d, $empreinte, $cle) {
            $t = DB::table('modAct_taches')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Tâche introuvable.');
            self::tache($id);
            if ($action !== 'commenter' && ! hash_equals(self::empreinte($id), $empreinte)) {
                throw new RegleMetier('La tâche a changé depuis l’ouverture de la page : rechargez-la.');
            }
            $role = self::role($id);
            $u = Auth::user();
            $executant = $role !== null && $role !== 'observateur';
            $motif = trim((string) ($d['motif'] ?? ''));
            $verrouillee = in_array($t->statut, ['realise', 'annule'], true);
            if ($verrouillee && $action !== 'commenter') {
                throw new RegleMetier('Cette tâche est '.($t->statut === 'realise' ? 'réalisée' : 'annulée').' : seul un commentaire reste possible.');
            }
            $v = [];
            switch ($action) {
                case 'demarrer':
                    if (! self::gestionnaire() && ! $executant) {
                        throw new RegleMetier('Vous n’êtes pas affecté à cette tâche.');
                    }
                    if (! in_array($t->statut, ['planifie', 'reporte'], true)) {
                        throw new RegleMetier('Seule une tâche planifiée ou reportée peut démarrer.');
                    }
                    $v = ['statut' => 'en_cours'];
                    self::pointage($id, 'demarrer');
                    break;
                case 'terminer':
                    $gerant = $u->can('ACT.cloturer') && $executant;
                    if (! self::gestionnaire() && ! $gerant && ! ($u->roleCanonique() === 'technicien' && $executant)) {
                        throw new RegleMetier('Terminer une tâche est réservé au gestionnaire ou au gérant d’équipe affecté.');
                    }
                    $duree = ($d['duree_reelle_min'] ?? '') !== '' ? (int) $d['duree_reelle_min'] : null;
                    if ($duree !== null && ($duree < 0 || $duree > 525600)) {
                        throw new RegleMetier('Durée réelle invalide (0 à 525 600 minutes).');
                    }
                    $v = ['statut' => 'realise', 'date_realisation' => now(), 'duree_reelle_min' => $duree];
                    self::pointage($id, 'terminer', $motif ?: null, $duree);
                    break;
                case 'annuler':
                    if (! self::gestionnaire()) {
                        throw new RegleMetier('L’annulation est réservée au gestionnaire.');
                    }
                    if (mb_strlen($motif) < 3) {
                        throw new RegleMetier('Indiquez le motif de l’annulation.');
                    }
                    $v = ['statut' => 'annule'];
                    self::pointage($id, 'commenter', 'Annulation : '.$motif);
                    break;
                case 'reporter':
                    if (! self::planificateur() && ! (self::gestionnaire() || $executant && $u->can('ACT.cloturer'))) {
                        throw new RegleMetier('Le report est réservé aux responsables de planning.');
                    }
                    if (mb_strlen($motif) < 3) {
                        throw new RegleMetier('Indiquez le motif du report.');
                    }
                    $debut = Carbon::parse(str_replace('T', ' ', (string) ($d['debut'] ?? '')));
                    $duree = (int) ($d['duree_min'] ?? 0);
                    if ($duree < 1 || $duree > 1440) {
                        throw new RegleMetier('La durée doit être comprise entre 1 et 1 440 minutes.');
                    }
                    if ($t->date_debut_prevue && $debut->lte(Carbon::parse($t->date_debut_prevue))) {
                        throw new RegleMetier('Le report doit être strictement postérieur au début précédent.');
                    }
                    $v = ['statut' => 'reporte', 'date_debut_prevue' => $debut->format('Y-m-d H:i:s'), 'date_fin_prevue' => $debut->copy()->addMinutes($duree)->format('Y-m-d H:i:s'), 'duree_estimee_min' => $duree];
                    if (! empty($d['deplacer_echeance'])) {
                        $v['date_echeance'] = $debut->copy()->addMinutes($duree)->format('Y-m-d H:i:s');
                    }
                    self::pointage($id, 'commenter', 'Report : '.$motif);
                    break;
                case 'commenter':
                    if ($role === null && ! self::planificateur()) {
                        throw new RegleMetier('Vous n’êtes pas affecté à cette tâche.');
                    }
                    if (mb_strlen($motif) < 1) {
                        throw new RegleMetier('Écrivez un commentaire.');
                    }
                    self::pointage($id, 'commenter', mb_substr($motif, 0, 5000));
                    break;
                default:
                    throw new RegleMetier('Action inconnue.');
            }
            if ($v) {
                DB::table('modAct_taches')->where('id', $id)->update($v + ['maj_le' => now()]);
            }
            self::journal($id, $action, $motif, array_intersect_key((array) $t, $v) ?: null, $v ?: ['commentaire' => $motif], $cle);
        });
    }

    // ----- Routines et génération (RG-ACT-010 à 015)

    public static function apercu(int $routine, string $du, string $au): array
    {
        $r = DB::table('modAct_routines')->where('id', $routine)->first() ?? throw new RegleMetier('Routine introuvable.');
        if (! $r->actif) {
            throw new RegleMetier('Cette routine est inactive.');
        }
        if (! $r->id_responsable && ! $r->id_equipe) {
            throw new RegleMetier('Définissez un responsable ou une équipe pour la routine avant de générer.');
        }
        $taches = DB::table('modAct_routine_taches')->where('id_routine', $routine)->where('actif', 1)->orderBy('id')->get();
        $lignes = [];
        $nouvelles = 0;
        foreach ($taches as $rt) {
            foreach (Recurrence::occurrences($rt, $r, $du, $au) as [$deb, $fin, $ech]) {
                $existe = DB::table('modAct_occurrences_origine')->where('id_routine_tache', $rt->id)->where('debut_origine', $deb->format('Y-m-d H:i:s'))->exists()
                    || DB::table('modAct_taches')->where('id_routine_tache', $rt->id)->where('date_debut_prevue', $deb->format('Y-m-d H:i:s'))->exists();
                $lignes[] = ['rt' => $rt->id, 'nom' => $rt->nom, 'debut' => $deb->format('Y-m-d H:i:s'), 'fin' => $fin->format('Y-m-d H:i:s'), 'echeance' => $ech->format('Y-m-d H:i:s'), 'existe' => $existe];
                $nouvelles += $existe ? 0 : 1;
                if (count($lignes) > Recurrence::MAX_OCCURRENCES) {
                    throw new RegleMetier('Plus de '.Recurrence::MAX_OCCURRENCES.' occurrences : réduisez la période.');
                }
            }
        }
        $config = Empreinte::de(['r' => $r, 't' => $taches->all(), 'du' => $du, 'au' => $au]);

        return ['routine' => $r, 'lignes' => $lignes, 'nouvelles' => $nouvelles, 'empreinte' => $config, 'du' => $du, 'au' => $au];
    }

    /** Confirmation : configuration inchangée, ticket à usage unique, écritures atomiques. */
    public static function generer(int $routine, string $du, string $au, string $empreinte, string $cle): int
    {
        if (! self::planificateur()) {
            throw new RegleMetier('Génération réservée aux responsables de planning.');
        }

        return DB::transaction(function () use ($routine, $du, $au, $empreinte, $cle) {
            DB::table('modAct_routines')->where('id', $routine)->lockForUpdate()->first();
            $a = self::apercu($routine, $du, $au);
            if (! hash_equals($a['empreinte'], $empreinte)) {
                throw new RegleMetier('La routine a changé depuis l’aperçu : vérifiez-le de nouveau.');
            }
            $r = $a['routine'];
            $rts = DB::table('modAct_routine_taches')->where('id_routine', $routine)->get()->keyBy('id');
            $n = 0;
            foreach ($a['lignes'] as $l) {
                if ($l['existe']) {
                    continue;
                }
                $rt = $rts[$l['rt']];
                $id = DB::table('modAct_taches')->insertGetId(['source_type' => 'routine', 'id_routine' => $routine, 'id_routine_tache' => $rt->id, 'nom' => $rt->nom,
                    'description' => $rt->description, 'id_categorie' => $rt->id_categorie, 'priorite' => $rt->priorite, 'statut' => 'planifie',
                    'date_debut_prevue' => $l['debut'], 'date_fin_prevue' => $l['fin'], 'date_echeance' => $l['echeance'], 'charge_jh' => $rt->charge_jh,
                    'duree_estimee_min' => $rt->duree_estimee_min, 'cree_par' => Auth::id()]);
                $aff = [];
                if ($r->id_responsable) {
                    $aff[] = ['id_tache' => $id, 'type_cible' => 'user', 'id_user' => $r->id_responsable, 'id_equipe' => null, 'role_affectation' => 'responsable'];
                }
                if ($r->id_equipe) {
                    $aff[] = ['id_tache' => $id, 'type_cible' => 'equipe', 'id_user' => null, 'id_equipe' => $r->id_equipe, 'role_affectation' => 'executeur'];
                }
                DB::table('modAct_tache_affectations')->insert($aff);
                DB::table('modAct_occurrences_origine')->insert(['id_tache' => $id, 'id_routine_tache' => $rt->id, 'debut_origine' => $l['debut']]);
                self::journal($id, 'generer', '', null, ['routine' => $routine, 'debut' => $l['debut']], $n === 0 ? $cle : substr($cle, 0, 40).'-'.Str::random(20));
                $n++;
            }
            Journal::enregistrer('routine.generation', 'ACT', 'routine', $routine, null, ['du' => $du, 'au' => $au, 'creees' => $n]);

            return $n;
        });
    }

    public static function enregistrerRoutine(?int $id, array $d): int
    {
        $v = ['titre' => trim((string) $d['titre']), 'description' => ($d['description'] ?? null) ?: null, 'actif' => empty($d['actif']) ? 0 : 1,
            'date_debut' => ($d['date_debut'] ?? null) ?: null, 'date_fin' => ($d['date_fin'] ?? null) ?: null, 'id_equipe' => ($d['id_equipe'] ?? null) ?: null,
            'id_responsable' => ($d['id_responsable'] ?? null) ?: null, 'couleur_fond' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($d['couleur_fond'] ?? '')) ? $d['couleur_fond'] : '#EAF4FF'];
        if ($v['titre'] === '') {
            throw new RegleMetier('Le titre est obligatoire.');
        }
        if ($v['date_debut'] && $v['date_fin'] && $v['date_fin'] < $v['date_debut']) {
            throw new RegleMetier('La fin de validité précède le début.');
        }
        if ($id) {
            DB::table('modAct_routines')->where('id', $id)->update($v + ['maj_le' => now()]);
        } else {
            $id = DB::table('modAct_routines')->insertGetId($v + ['cree_par' => Auth::id()]);
        }
        Journal::enregistrer('routine.enregistrement', 'ACT', 'routine', $id, apres: $v);

        return $id;
    }

    public static function enregistrerTacheRoutine(int $routine, ?int $id, array $d): void
    {
        $type = $d['type_recurrence'] ?? '';
        if (! isset(Recurrence::TYPES[$type])) {
            throw new RegleMetier('Récurrence invalide.');
        }
        $h = fn ($k) => ($d[$k] ?? '') !== '' ? substr($d[$k], 0, 5).':00' : null;
        $v = ['id_routine' => $routine, 'nom' => trim((string) $d['nom']), 'description' => ($d['description'] ?? null) ?: null, 'id_categorie' => ($d['id_categorie'] ?? null) ?: null,
            'priorite' => isset(self::PRIORITES[$d['priorite'] ?? '']) ? $d['priorite'] : 'normale', 'duree_estimee_min' => ($d['duree_estimee_min'] ?? '') !== '' ? max(1, (int) $d['duree_estimee_min']) : 60,
            'horaire_debut' => $h('horaire_debut'), 'horaire_fin' => $h('horaire_fin'), 'delai_echeance_min' => ($d['delai_echeance_min'] ?? '') !== '' ? max(0, (int) $d['delai_echeance_min']) : null,
            'type_recurrence' => $type, 'jours_semaine' => implode(',', array_intersect((array) ($d['jours_semaine'] ?? []), array_keys(Recurrence::JOURS))) ?: null,
            'jours_mois' => implode(',', Recurrence::liste($d['jours_mois'] ?? '', 1, 31)) ?: null, 'jour_mois' => null,
            'intervalle_min' => ($d['intervalle_min'] ?? '') !== '' ? (int) $d['intervalle_min'] : null, 'intervalle_semaines' => max(1, (int) ($d['intervalle_semaines'] ?? 1)),
            'mois_annee' => implode(',', Recurrence::liste($d['mois_annee'] ?? '', 1, 12)) ?: null, 'actif' => empty($d['actif']) ? 0 : 1];
        if ($v['nom'] === '') {
            throw new RegleMetier('Le nom de la tâche est obligatoire.');
        }
        match ($type) {
            'hebdo' => $v['jours_semaine'] ?: throw new RegleMetier('Choisissez au moins un jour de la semaine.'),
            'mensuel' => $v['jours_mois'] ?: throw new RegleMetier('Indiquez les jours du mois (ex. 1, 15, 31).'),
            'annuel' => ($v['mois_annee'] && $v['jours_mois']) ?: throw new RegleMetier('Indiquez les mois et les jours.'),
            'intervalle' => ($v['intervalle_min'] > 0 && $v['horaire_debut'] && $v['horaire_fin'] && $v['horaire_fin'] > $v['horaire_debut']) ?: throw new RegleMetier('Plage horaire (fin après début) et intervalle en minutes obligatoires.'),
            default => null,
        };
        $id ? DB::table('modAct_routine_taches')->where('id', $id)->where('id_routine', $routine)->update($v) : DB::table('modAct_routine_taches')->insert($v);
        Journal::enregistrer('routine.tache', 'ACT', 'routine', $routine, apres: $v);
    }

    // ----- Référentiels (RG-ACT-016) et occupations (RG-ACT-017)

    public static function enregistrerEquipe(?int $id, array $d, array $membres): int
    {
        $v = ['nom' => trim((string) $d['nom']), 'description' => ($d['description'] ?? null) ?: null, 'couleur' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($d['couleur'] ?? '')) ? $d['couleur'] : '#2d89ef', 'actif' => empty($d['actif']) ? 0 : 1];
        if ($v['nom'] === '') {
            throw new RegleMetier('Le nom de l’équipe est obligatoire.');
        }

        return DB::transaction(function () use ($id, $v, $membres) {
            if (DB::table('modAct_equipes')->where('nom', $v['nom'])->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
                throw new RegleMetier('Une équipe porte déjà ce nom.');
            }
            $id ? DB::table('modAct_equipes')->where('id', $id)->update($v) : $id = DB::table('modAct_equipes')->insertGetId($v);
            $membres = array_values(array_unique(array_map('intval', $membres)));
            DB::table('modAct_equipe_membres')->where('id_equipe', $id)->whereNotIn('id_user', $membres ?: [0])->delete();
            foreach ($membres as $m) {
                DB::table('modAct_equipe_membres')->insertOrIgnore(['id_equipe' => $id, 'id_user' => $m]);
            }
            Journal::enregistrer('equipe.enregistrement', 'ACT', 'equipe', $id, apres: $v + ['membres' => $membres]);

            return $id;
        });
    }

    public static function enregistrerCategorie(?int $id, array $d): void
    {
        $v = ['libelle' => trim((string) $d['libelle']), 'couleur' => ($d['couleur'] ?? null) ?: null, 'actif' => empty($d['actif']) ? 0 : 1];
        if ($v['libelle'] === '') {
            throw new RegleMetier('Le libellé est obligatoire.');
        }
        if (DB::table('modAct_categories')->where('libelle', $v['libelle'])->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
            throw new RegleMetier('Cette catégorie existe déjà.');
        }
        $id ? DB::table('modAct_categories')->where('id', $id)->update($v) : DB::table('modAct_categories')->insert($v);
    }

    public static function enregistrerProjet(?int $id, array $d): int
    {
        $v = ['code' => ($d['code'] ?? null) ?: null, 'titre' => trim((string) $d['titre']), 'description' => ($d['description'] ?? null) ?: null,
            'date_debut' => ($d['date_debut'] ?? null) ?: null, 'date_cible' => ($d['date_cible'] ?? null) ?: null,
            'statut' => in_array($d['statut'] ?? 'planifie', ['planifie', 'en_cours', 'termine', 'annule'], true) ? ($d['statut'] ?? 'planifie') : 'planifie',
            'priorite' => isset(self::PRIORITES[$d['priorite'] ?? '']) ? $d['priorite'] : 'normale',
            'id_responsable' => ($d['id_responsable'] ?? null) ?: null, 'id_equipe' => ($d['id_equipe'] ?? null) ?: null];
        if ($v['titre'] === '') {
            throw new RegleMetier('Le titre du projet est obligatoire.');
        }
        if ($id) {
            DB::table('modAct_projets')->where('id', $id)->update($v + ['maj_le' => now()]);
        } else {
            $id = DB::table('modAct_projets')->insertGetId($v + ['cree_par' => Auth::id()]);
        }
        Journal::enregistrer('projet.enregistrement', 'ACT', 'projet', $id, apres: $v);

        return $id;
    }

    public static function ajouterOccupation(array $d): void
    {
        $deb = Carbon::parse(str_replace('T', ' ', (string) $d['debut']));
        $fin = Carbon::parse(str_replace('T', ' ', (string) $d['fin']));
        if ($fin->lte($deb)) {
            throw new RegleMetier('La fin doit suivre le début.');
        }
        if (! isset(self::OCCUPATIONS[$d['type'] ?? ''])) {
            throw new RegleMetier('Type invalide.');
        }
        DB::table('modAct_occupations')->insert(['id_user' => (int) $d['id_user'], 'titre' => mb_substr(trim((string) $d['titre']) ?: self::OCCUPATIONS[$d['type']], 0, 200),
            'debut' => $deb, 'fin' => $fin, 'type' => $d['type'], 'commentaire' => ($d['commentaire'] ?? null) ?: null, 'cree_par' => Auth::id()]);
    }
}

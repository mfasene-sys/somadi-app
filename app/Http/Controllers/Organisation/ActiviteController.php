<?php

namespace App\Http\Controllers\Organisation;

use App\Domaine\Organisation\Activites;
use App\Domaine\Organisation\Recurrence;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActiviteController extends Controller
{
    private function listes(): array
    {
        return [
            'utilisateurs' => DB::table('utilisateurs')->where('actif', 1)->orderBy('prenom')->orderBy('nom')->get(['id', 'nom', 'prenom', 'role']),
            'equipes' => DB::table('modAct_equipes')->where('actif', 1)->orderBy('nom')->get(),
            'categories' => DB::table('modAct_categories')->where('actif', 1)->orderBy('libelle')->get(),
            'projets' => DB::table('modAct_projets')->whereIn('statut', ['planifie', 'en_cours'])->orderBy('titre')->get(),
        ];
    }

    /** Vue du jour : mes tâches et les tâches du jour visibles. */
    public function index(): View
    {
        $u = Auth::user();
        $equipes = DB::table('modAct_equipe_membres')->where('id_user', $u->id)->pluck('id_equipe')->all();
        $mes = Activites::visibles()->whereIn('t.statut', ['planifie', 'en_cours', 'reporte'])
            ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('modAct_tache_affectations as a')->whereColumn('a.id_tache', 't.id')->where('a.role_affectation', '<>', 'observateur')
                ->where(fn ($w) => $w->where(fn ($x) => $x->where('a.type_cible', 'user')->where('a.id_user', $u->id))->orWhere(fn ($x) => $x->where('a.type_cible', 'equipe')->whereIn('a.id_equipe', $equipes ?: [0]))))
            ->where(fn ($q) => $q->whereNull('t.date_debut_prevue')->orWhere('t.date_debut_prevue', '<', today()->addDays(8)))
            ->orderBy('t.date_debut_prevue')->limit(200)->get();
        $jour = Activites::visibles()->whereBetween('t.date_debut_prevue', [today(), today()->endOfDay()])->orderBy('t.date_debut_prevue')->get();
        $retard = Activites::visibles()->whereIn('t.statut', ['planifie', 'en_cours', 'reporte'])->where('t.date_echeance', '<', now())->count();

        return view('organisation.activites.index', ['mes' => $mes, 'jour' => $jour, 'retard' => $retard]);
    }

    public function liste(Request $request): View
    {
        $f = $request->validate(['statut' => ['nullable', Rule::in(array_keys(Activites::STATUTS))], 'projet' => ['nullable', 'integer'], 'routine' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'], 'du' => ['nullable', 'date'], 'au' => ['nullable', 'date']]);
        $taches = Activites::visibles()
            ->when($f['statut'] ?? null, fn ($q, $s) => $q->where('t.statut', $s))
            ->when($f['projet'] ?? null, fn ($q, $p) => $q->where('t.id_projet', $p))
            ->when($f['routine'] ?? null, fn ($q, $r) => $q->where('t.id_routine', $r))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where('t.nom', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $t).'%'))
            ->when($f['du'] ?? null, fn ($q, $d) => $q->where('t.date_debut_prevue', '>=', $d))
            ->when($f['au'] ?? null, fn ($q, $d) => $q->where('t.date_debut_prevue', '<', Carbon::parse($d)->addDay()))
            ->orderByDesc('t.date_debut_prevue')->orderByDesc('t.id')->paginate(50)->withQueryString();

        return view('organisation.activites.liste', ['taches' => $taches, 'f' => $f] + $this->listes() + ['routines' => DB::table('modAct_routines')->orderBy('titre')->get()]);
    }

    public function planning(Request $request): View
    {
        $lundi = Carbon::parse($request->query('semaine', today()->toDateString()))->startOfWeek();
        $taches = Activites::visibles()->whereBetween('t.date_debut_prevue', [$lundi, $lundi->copy()->addDays(7)])->whereNotIn('t.statut', ['annule'])->orderBy('t.date_debut_prevue')->get();
        $aff = DB::table('modAct_tache_affectations')->whereIn('id_tache', $taches->pluck('id')->all() ?: [0])->get()->groupBy('id_tache');
        $membres = DB::table('modAct_equipe_membres')->get()->groupBy('id_equipe');
        $parPersonne = [];
        foreach ($taches as $t) {
            $users = [];
            foreach ($aff[$t->id] ?? [] as $a) {
                if ($a->type_cible === 'user') {
                    $users[] = $a->id_user;
                } else {
                    foreach ($membres[$a->id_equipe] ?? [] as $m) {
                        $users[] = $m->id_user;
                    }
                }
            }
            foreach (array_unique($users) as $u) {
                $parPersonne[$u][Carbon::parse($t->date_debut_prevue)->toDateString()][] = $t;
            }
        }
        $occupations = DB::table('modAct_occupations')->where('debut', '<', $lundi->copy()->addDays(7))->where('fin', '>', $lundi)->get();
        foreach ($occupations as $o) {
            $parPersonne[$o->id_user] ??= [];
        }
        $personnes = DB::table('utilisateurs')->whereIn('id', array_keys($parPersonne) ?: [0])->orderBy('prenom')->get(['id', 'nom', 'prenom']);

        return view('organisation.activites.planning', compact('lundi', 'parPersonne', 'personnes', 'occupations') + $this->listes());
    }

    public function create(): View
    {
        abort_unless(Activites::planificateur(), 403);

        return view('organisation.activites.form', ['t' => null, 'aff' => collect()] + $this->listes());
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:10000'], 'id_categorie' => ['nullable', 'integer'], 'id_projet' => ['nullable', 'integer'],
            'priorite' => ['required', Rule::in(array_keys(Activites::PRIORITES))], 'date_debut_prevue' => ['nullable', 'date'], 'date_fin_prevue' => ['nullable', 'date'],
            'date_echeance' => ['nullable', 'date'], 'duree_estimee_min' => ['nullable', 'integer', 'min:1', 'max:525600'], 'charge_jh' => ['nullable', 'string', 'max:10'],
            'responsable' => ['nullable', 'integer'], 'executeurs' => ['nullable', 'array'], 'executeurs.*' => ['integer'], 'equipes' => ['nullable', 'array'], 'equipes.*' => ['integer'],
            'observateurs' => ['nullable', 'array'], 'observateurs.*' => ['integer'],
        ], [], ['nom' => 'titre']);
    }

    public function store(Request $request): RedirectResponse
    {
        $id = Activites::creer($this->valider($request), (string) $request->input('_cle', Str::uuid()));

        return redirect()->route('activites.taches.show', $id)->with('succes', 'Tâche créée.');
    }

    public function show(int $id): View
    {
        $t = Activites::tache($id);

        return view('organisation.activites.show', [
            't' => $t, 'aff' => Activites::affectations($id), 'role' => Activites::role($id), 'empreinte' => Activites::empreinte($id),
            'pointages' => DB::table('modAct_tache_pointages as p')->leftJoin('utilisateurs as u', 'u.id', '=', 'p.id_user')->where('p.id_tache', $id)->orderByDesc('p.id')
                ->get(['p.*', DB::raw("TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) as auteur")]),
            'gestionnaire' => Activites::gestionnaire(), 'planificateur' => Activites::planificateur(),
        ]);
    }

    public function edit(int $id): View
    {
        abort_unless(Activites::planificateur(), 403);

        return view('organisation.activites.form', ['t' => Activites::tache($id), 'aff' => Activites::affectations($id), 'empreinte' => Activites::empreinte($id)] + $this->listes());
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Activites::modifier($id, $this->valider($request), (string) $request->input('empreinte'), (string) $request->input('_cle', Str::uuid()));

        return redirect()->route('activites.taches.show', $id)->with('succes', 'Tâche modifiée.');
    }

    public function agir(Request $request, int $id, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['demarrer', 'terminer', 'annuler', 'reporter', 'commenter'], true), 404);
        $d = $request->validate(['motif' => ['nullable', 'string', 'max:5000'], 'duree_reelle_min' => ['nullable', 'integer'], 'debut' => ['nullable', 'date'],
            'duree_min' => ['nullable', 'integer'], 'deplacer_echeance' => ['nullable', 'boolean'], 'empreinte' => ['nullable', 'string'], '_cle' => ['required', 'string', 'max:64']]);
        Activites::agir($id, $action, $d, (string) ($d['empreinte'] ?? ''), $d['_cle']);
        $msg = ['demarrer' => 'Tâche démarrée.', 'terminer' => 'Tâche terminée.', 'annuler' => 'Tâche annulée.', 'reporter' => 'Tâche reportée.', 'commenter' => 'Commentaire ajouté.'][$action];

        return back()->with('succes', $msg);
    }

    // ----- Routines

    public function routines(): View
    {
        return view('organisation.activites.routines', ['routines' => DB::table('modAct_routines as r')->leftJoin('modAct_equipes as e', 'e.id', '=', 'r.id_equipe')
            ->leftJoinSub(DB::table('modAct_routine_taches')->groupBy('id_routine')->select('id_routine', DB::raw('COUNT(*) n')), 'x', 'x.id_routine', '=', 'r.id')
            ->orderByDesc('r.actif')->orderBy('r.titre')->get(['r.*', 'e.nom as equipe', 'x.n'])] + $this->listes());
    }

    public function routine(Request $request, int $id): View
    {
        $r = DB::table('modAct_routines')->where('id', $id)->first() ?? abort(404);
        $apercu = null;
        if ($request->filled(['du', 'au'])) {
            try {
                $apercu = Activites::apercu($id, $request->query('du'), $request->query('au'));
            } catch (\App\Exceptions\RegleMetier $e) {
                session()->now('erreur', $e->getMessage());
            }
        }

        return view('organisation.activites.routine', ['r' => $r, 'taches' => DB::table('modAct_routine_taches')->where('id_routine', $id)->orderBy('id')->get(), 'apercu' => $apercu,
            'edition' => $request->integer('tache') ? DB::table('modAct_routine_taches')->where('id', $request->integer('tache'))->where('id_routine', $id)->first() : null] + $this->listes());
    }

    public function enregistrerRoutine(Request $request, ?int $id = null): RedirectResponse
    {
        abort_unless(Activites::planificateur(), 403);
        $d = $request->validate(['titre' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'], 'actif' => ['nullable', 'boolean'],
            'date_debut' => ['nullable', 'date'], 'date_fin' => ['nullable', 'date'], 'id_equipe' => ['nullable', 'integer'], 'id_responsable' => ['nullable', 'integer'], 'couleur_fond' => ['nullable', 'string', 'max:7']]);
        $id = Activites::enregistrerRoutine($id, $d);

        return redirect()->route('activites.routines.show', $id)->with('succes', 'Routine enregistrée.');
    }

    public function enregistrerTacheRoutine(Request $request, int $id, ?int $tache = null): RedirectResponse
    {
        abort_unless(Activites::planificateur(), 403);
        $d = $request->validate(['nom' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'], 'id_categorie' => ['nullable', 'integer'],
            'priorite' => ['nullable', 'string'], 'duree_estimee_min' => ['nullable', 'integer'], 'horaire_debut' => ['nullable', 'string', 'max:8'], 'horaire_fin' => ['nullable', 'string', 'max:8'],
            'delai_echeance_min' => ['nullable', 'integer'], 'type_recurrence' => ['required', Rule::in(array_keys(Recurrence::TYPES))], 'jours_semaine' => ['nullable', 'array'],
            'jours_mois' => ['nullable', 'string', 'max:120'], 'intervalle_min' => ['nullable', 'integer'], 'intervalle_semaines' => ['nullable', 'integer', 'min:1', 'max:52'],
            'mois_annee' => ['nullable', 'string', 'max:50'], 'actif' => ['nullable', 'boolean']]);
        Activites::enregistrerTacheRoutine($id, $tache, $d);

        return redirect()->route('activites.routines.show', $id)->with('succes', 'Tâche de routine enregistrée.');
    }

    public function generer(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['du' => ['required', 'date'], 'au' => ['required', 'date'], 'empreinte' => ['required', 'string'], '_cle' => ['required', 'string', 'max:64'], 'confirmation' => ['accepted']],
            ['confirmation.accepted' => 'Cochez la case de confirmation.']);
        $n = Activites::generer($id, $d['du'], $d['au'], $d['empreinte'], $d['_cle']);

        return redirect()->route('activites.routines.show', $id)->with('succes', "$n tâche(s) créée(s). Les occurrences déjà présentes n’ont pas été recréées.");
    }

    // ----- Équipes, catégories, projets, occupations

    public function equipes(): View
    {
        return view('organisation.activites.equipes', [
            'liste' => DB::table('modAct_equipes')->orderByDesc('actif')->orderBy('nom')->get(),
            'membres' => DB::table('modAct_equipe_membres')->get()->groupBy('id_equipe'),
            'toutesCategories' => DB::table('modAct_categories')->orderByDesc('actif')->orderBy('libelle')->get(),
        ] + $this->listes());
    }

    public function enregistrerEquipe(Request $request, ?int $id = null): RedirectResponse
    {
        abort_unless(Activites::planificateur(), 403);
        $d = $request->validate(['nom' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'couleur' => ['nullable', 'string', 'max:7'],
            'actif' => ['nullable', 'boolean'], 'membres' => ['nullable', 'array'], 'membres.*' => ['integer']]);
        Activites::enregistrerEquipe($id, $d, $d['membres'] ?? []);

        return back()->with('succes', 'Équipe enregistrée.');
    }

    public function enregistrerCategorie(Request $request, ?int $id = null): RedirectResponse
    {
        abort_unless(Activites::planificateur(), 403);
        Activites::enregistrerCategorie($id, $request->validate(['libelle' => ['required', 'string', 'max:100'], 'couleur' => ['nullable', 'string', 'max:20'], 'actif' => ['nullable', 'boolean']]));

        return back()->with('succes', 'Catégorie enregistrée.');
    }

    public function projets(): View
    {
        return view('organisation.activites.projets', ['liste' => DB::table('modAct_projets as p')->leftJoin('utilisateurs as u', 'u.id', '=', 'p.id_responsable')
            ->leftJoinSub(DB::table('modAct_taches')->whereNotNull('id_projet')->groupBy('id_projet')->select('id_projet', DB::raw('COUNT(*) n'), DB::raw("SUM(statut='realise') faites")), 'x', 'x.id_projet', '=', 'p.id')
            ->orderByRaw("FIELD(p.statut,'en_cours','planifie','termine','annule')")->orderBy('p.date_cible')
            ->get(['p.*', DB::raw("TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) as responsable"), 'x.n', 'x.faites'])] + $this->listes());
    }

    public function enregistrerProjet(Request $request, ?int $id = null): RedirectResponse
    {
        abort_unless(Activites::planificateur(), 403);
        Activites::enregistrerProjet($id, $request->validate(['code' => ['nullable', 'string', 'max:30'], 'titre' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'date_debut' => ['nullable', 'date'], 'date_cible' => ['nullable', 'date'], 'statut' => ['nullable', 'string'], 'priorite' => ['nullable', 'string'],
            'id_responsable' => ['nullable', 'integer'], 'id_equipe' => ['nullable', 'integer']]));

        return back()->with('succes', 'Projet enregistré.');
    }

    public function ajouterOccupation(Request $request): RedirectResponse
    {
        abort_unless(Activites::planificateur(), 403);
        Activites::ajouterOccupation($request->validate(['id_user' => ['required', 'integer'], 'titre' => ['nullable', 'string', 'max:200'], 'debut' => ['required', 'date'], 'fin' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(Activites::OCCUPATIONS))], 'commentaire' => ['nullable', 'string', 'max:1000']]));

        return back()->with('succes', 'Occupation ajoutée au planning.');
    }
}

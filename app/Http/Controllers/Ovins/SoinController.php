<?php

namespace App\Http\Controllers\Ovins;

use App\Domaine\Ovins\Referentiels;
use App\Domaine\Ovins\Soins;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SoinController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['site' => ['nullable', 'integer'], 'lot' => ['nullable', 'integer'], 'vue' => ['nullable', Rule::in(['a_faire', 'historique'])]]);
        $vue = $f['vue'] ?? 'a_faire';
        $q = Soins::requete($f);
        $today = today()->toDateString();
        if ($vue === 'a_faire') {
            $soins = $q->where('r.statut', 'planifie')->where('r.date_soin', '<=', today()->addDays(14)->toDateString())->orderBy('r.date_soin')->orderBy('r.id_soin')->limit(500)->get();
            $bruts = DB::table('OVINS_soins_realises')->whereIn('id_soin', $soins->pluck('id_soin'))->get()->keyBy('id_soin');
            $soins->each(fn ($x) => $x->empreinte = \App\Support\Empreinte::de($bruts[$x->id_soin] ?? null));
            $groupes = ['En retard' => $soins->where('date_soin', '<', $today), 'Aujourd’hui' => $soins->where('date_soin', $today), '14 prochains jours' => $soins->where('date_soin', '>', $today)];
        } else {
            $soins = $q->where('r.statut', '<>', 'planifie')->orderByDesc('r.date_soin')->orderByDesc('r.id_soin')->paginate(50)->withQueryString();
            $groupes = null;
        }

        return view('ovins.soins.index', [
            'vue' => $vue, 'f' => $f, 'soins' => $soins, 'groupes' => $groupes,
            'sites' => Referentiels::sites(true), 'lots' => Referentiels::lots(true),
            'types' => DB::table('OVINS_soins_types')->orderBy('nom_soin')->get(),
            'animaux' => DB::table('OVINS_animaux')->where('statut', 'en_ferme')->orderBy('code_animal')->get(['id_animal', 'code_animal', 'nom_animal']),
            'modeles' => DB::table('OVINS_modeles_soins')->where('actif', 1)->orderBy('nom_modele')->get(),
            'peutAnnulerRealisation' => in_array(auth()->user()->roleCanonique(), ['admin', 'technicien'], true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate(['cible' => ['required', Rule::in(['lot', 'animal'])], 'id_cible' => ['required', 'integer'], 'id_type_soin' => ['required', 'integer'],
            'date_soin' => ['required', 'date'], 'dose' => ['nullable', 'string', 'max:30'], 'commentaire' => ['nullable', 'string', 'max:2000'], 'realise' => ['nullable', 'boolean']],
            [], ['id_cible' => $request->input('cible') === 'animal' ? 'animal' : 'lot', 'id_type_soin' => 'soin']);
        DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);
            Soins::saisir($d);
        });

        return back()->with('succes', ! empty($d['realise']) ? 'Soin enregistré comme réalisé.' : 'Soin planifié.');
    }

    public function appliquer(Request $request): RedirectResponse
    {
        $d = $request->validate(['id_modele' => ['required', 'integer'], 'cible' => ['required', Rule::in(['lot', 'animal'])], 'id_cible' => ['required', 'integer'], 'date_reference' => ['required', 'date']],
            [], ['id_modele' => 'modèle', 'id_cible' => 'cible']);
        $r = Soins::appliquer((int) $d['id_modele'], $d['cible'], (int) $d['id_cible'], $d['date_reference']);

        return back()->with('succes', "{$r['crees']} soin(s) planifié(s)".($r['ignores'] ? ", {$r['ignores']} déjà présent(s) non recréé(s)" : '').'.');
    }

    public function realise(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['empreinte' => ['required', 'string'], 'date' => ['nullable', 'date']]);
        Soins::marquerRealise($id, $d['empreinte'], $d['date'] ?? null);

        return back()->with('succes', 'Soin marqué réalisé.');
    }

    public function annuler(Request $request, int $id): RedirectResponse
    {
        Soins::annuler($id, (string) $request->validate(['motif' => ['required', 'string', 'max:500']])['motif']);

        return back()->with('succes', 'Soin annulé.');
    }

    /** RG-OVS-011 : réservé à l'administrateur et au technicien. */
    public function retour(Request $request, int $id): RedirectResponse
    {
        abort_unless(in_array($request->user()->roleCanonique(), ['admin', 'technicien'], true), 403);
        Soins::annulerRealisation($id, (string) $request->validate(['motif' => ['required', 'string', 'max:500']])['motif']);

        return back()->with('succes', 'Réalisation annulée : le soin est de nouveau planifié.');
    }

    public function catalogue(): View
    {
        return view('ovins.soins.catalogue', [
            'types' => DB::table('OVINS_soins_types')->orderBy('nom_soin')->get(),
            'modeles' => DB::table('OVINS_modeles_soins as m')->leftJoin('OVINS_modeles_soins_lignes as l', 'l.id_modele', '=', 'm.id_modele')
                ->groupBy('m.id_modele', 'm.nom_modele', 'm.categorie', 'm.cible', 'm.actif', 'm.description', 'm.created_at', 'm.updated_at')
                ->orderByDesc('m.actif')->orderBy('m.nom_modele')->get(['m.*', DB::raw('COUNT(l.id_ligne) lignes')]),
        ]);
    }

    public function enregistrerType(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate(['nom_soin' => ['required', 'string', 'max:100'], 'categorie' => ['nullable', 'string', 'max:50'], 'voie' => ['nullable', 'string', 'max:50'],
            'delai_attente_jours' => ['nullable', 'integer', 'min:0', 'max:365'], 'commentaire' => ['nullable', 'string', 'max:2000']]);
        Soins::enregistrerType($id, $d);

        return redirect()->route('ovins.soins.catalogue')->with('succes', 'Catalogue mis à jour.');
    }

    public function modele(?int $id = null): View
    {
        return view('ovins.soins.modele', [
            'm' => $id ? (DB::table('OVINS_modeles_soins')->where('id_modele', $id)->first() ?? abort(404)) : null,
            'lignes' => $id ? Soins::lignesModele($id) : collect(),
            'types' => DB::table('OVINS_soins_types')->orderBy('nom_soin')->get(),
        ]);
    }

    public function enregistrerModele(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate(['nom_modele' => ['required', 'string', 'max:150'], 'categorie' => ['required', 'string', 'max:50'], 'cible' => ['required', Rule::in(['tous', 'lot', 'animal'])],
            'actif' => ['nullable', 'boolean'], 'description' => ['nullable', 'string', 'max:2000'], 'lignes' => ['nullable', 'array', 'max:60']]);
        $id = Soins::enregistrerModele($id, $d, $d['lignes'] ?? []);

        return redirect()->route('ovins.soins.modele', $id)->with('succes', 'Modèle enregistré.');
    }
}

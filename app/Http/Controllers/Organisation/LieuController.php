<?php

namespace App\Http\Controllers\Organisation;

use App\Domaine\Organisation\Infrastructures;
use App\Domaine\Organisation\Planning;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LieuController extends Controller
{
    public function index(): View
    {
        return view('organisation.lieux.index', ['lieux' => Infrastructures::lieux(), 'sites' => DB::table('OVINS_sites')->where('actif', 1)->orderBy('nom_site')->get()]);
    }

    public function show(int $id): View
    {
        return view('organisation.lieux.show', [
            'l' => Infrastructures::lieu($id), 'occupants' => Infrastructures::occupants($id),
            'periodes' => Planning::periodes($id), 'sites' => DB::table('OVINS_sites')->where('actif', 1)->orderBy('nom_site')->get(),
            'journal' => DB::table('modInfra_journal')->where('id_batiment', $id)->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40'], 'nom_batiment' => ['required', 'string', 'max:100'], 'id_site' => ['required', 'integer'],
            'type_lieu' => ['required', Rule::in(array_keys(Infrastructures::TYPES))], 'famille' => ['required', Rule::in(array_keys(Infrastructures::FAMILLES))],
            'espece' => ['nullable', 'string', 'max:50'], 'stade' => ['nullable', 'string', 'max:80'], 'surface_m2' => ['nullable', 'string', 'max:15'],
            'etat' => ['required', Rule::in(array_keys(Infrastructures::ETATS))], 'disponible_le' => ['nullable', 'date'], 'capacite' => ['required', 'integer', 'min:1'],
            'actif' => ['nullable', 'boolean'], 'observations' => ['nullable', 'string', 'max:2000'],
        ], [], ['id_site' => 'site', 'nom_batiment' => 'nom']);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->valider($request);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Infrastructures::enregistrer(null, $d);
        });

        return redirect()->route('organisation.lieux.show', $id)->with('succes', 'Emplacement créé.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Infrastructures::enregistrer($id, $this->valider($request), $request->filled('version') ? (int) $request->input('version') : null);

        return back()->with('succes', 'Emplacement mis à jour.');
    }

    public function repartition(Request $request): View
    {
        $f = $request->validate(['famille' => ['nullable', Rule::in(array_keys(Infrastructures::SOURCES))], 'source' => ['nullable', 'integer']]);
        $famille = $f['famille'] ?? 'volaille';
        $sources = Infrastructures::sources($famille);
        $source = ! empty($f['source']) ? $sources->firstWhere('id', (int) $f['source']) : null;

        return view('organisation.lieux.repartition', [
            'famille' => $famille, 'sources' => $sources, 'source' => $source,
            'positions' => $source ? Infrastructures::positions($famille, $source->id) : collect(),
            'destinations' => $source ? Infrastructures::destinations($famille, $source) : collect(),
            'empreinte' => $source ? Infrastructures::empreinte($famille, $source->id) : null,
        ]);
    }

    public function deplacer(Request $request): RedirectResponse
    {
        $d = $request->validate(['famille' => ['required', Rule::in(array_keys(Infrastructures::SOURCES))], 'source' => ['required', 'integer'], 'origine' => ['required', 'integer', 'min:0'],
            'destination' => ['required', 'integer', 'min:0'], 'quantite' => ['required', 'integer', 'min:1'], 'motif' => ['required', 'string', 'max:500'], 'empreinte' => ['required', 'string']]);
        DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);
            Infrastructures::deplacer($d['famille'], (int) $d['source'], (int) $d['origine'], (int) $d['destination'], (int) $d['quantite'], $d['motif'], $d['empreinte']);
        });

        return back()->with('succes', 'Répartition mise à jour.');
    }

    public function journal(): View
    {
        return view('organisation.lieux.journal', ['journal' => DB::table('modInfra_journal as j')->leftJoin('batiments_engraissement as b', 'b.id', '=', 'j.id_batiment')
            ->orderByDesc('j.id')->paginate(50, ['j.*', 'b.nom_batiment'])]);
    }

    // ----- Planning (IFP)

    public function planning(): View
    {
        return view('organisation.planning.index', [
            'periodes' => Planning::periodes(), 'historique' => Planning::periodes(null, false)->take(30), 'alertes' => Planning::alertes(),
            'lieux' => Infrastructures::lieux()->filter(fn ($l) => $l->code && $l->actif),
        ]);
    }

    public function planifier(Request $request): RedirectResponse
    {
        $d = $request->validate(['id_batiment' => ['required', 'integer'], 'nature' => ['required', Rule::in(array_keys(Planning::NATURES))], 'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date'], 'quantite' => ['nullable', 'integer', 'min:0'], 'libelle' => ['nullable', 'string', 'max:150'], 'commentaire' => ['nullable', 'string', 'max:2000']],
            [], ['id_batiment' => 'emplacement']);
        DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);
            Planning::creer($d);
        });

        return back()->with('succes', 'Période planifiée.');
    }

    public function modifierPeriode(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['date_debut' => ['required', 'date'], 'date_fin' => ['required', 'date'], 'quantite' => ['nullable', 'integer', 'min:0'],
            'libelle' => ['nullable', 'string', 'max:150'], 'commentaire' => ['nullable', 'string', 'max:2000'], 'version' => ['required', 'integer']]);
        Planning::modifier($id, $d, (int) $d['version']);

        return back()->with('succes', 'Période modifiée.');
    }

    public function transition(Request $request, int $id, string $action): RedirectResponse
    {
        $motif = (string) $request->input('motif', '');
        match ($action) {
            'annuler' => Planning::annuler($id, $motif),
            'cloturer' => Planning::cloturerReservation($id),
            'demarrer' => Planning::demarrer($id),
            'terminer' => Planning::terminer($id),
            'administrative' => $request->user()->estAdmin() ? Planning::clotureAdministrative($id, $motif) : abort(403),
            default => abort(404),
        };
        $msg = ['annuler' => 'Période annulée.', 'cloturer' => 'Réservation clôturée (aucun animal n’a été déplacé).', 'demarrer' => 'Intervention démarrée : l’emplacement est indisponible.',
            'terminer' => 'Intervention terminée : l’emplacement est de nouveau disponible.', 'administrative' => 'Clôture administrative enregistrée ; remettez l’emplacement en service si nécessaire.'][$action];

        return back()->with('succes', $msg);
    }
}

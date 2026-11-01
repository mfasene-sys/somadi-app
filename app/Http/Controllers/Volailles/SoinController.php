<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\Soins;
use App\Http\Controllers\Controller;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SoinController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'lot' => ['nullable', 'integer'], 'etat' => ['nullable', Rule::in(array_keys(Soins::ETATS))],
            'du' => ['nullable', 'date'], 'au' => ['nullable', 'date'],
        ]);
        $base = fn () => DB::table('modSoinVacc_soins as s')->join('modLotVol_lots_volailles as l', 'l.id', '=', 's.id_lot')
            ->leftJoin('modSoinVacc_catalogue as c', 'c.id', '=', 's.id_soin')
            ->when($f['lot'] ?? null, fn ($q, $lot) => $q->where('s.id_lot', $lot))
            ->select('s.*', 'l.nom_lot', 'c.nom as nom_soin');
        $aujourdhui = today()->toDateString();
        $retards = $base()->where('s.etat', 'planifie')->where('s.date_prevue', '<', $aujourdhui)->orderBy('s.date_prevue')->limit(200)->get();
        $dujour = $base()->where('s.date_prevue', $aujourdhui)->orderBy('l.nom_lot')->get();
        $du = $f['du'] ?? today()->addDay()->toDateString();
        $au = $f['au'] ?? today()->addDays(14)->toDateString();
        $periode = $base()->whereBetween('s.date_prevue', [$du, $au])->when($f['etat'] ?? null, fn ($q, $e) => $q->where('s.etat', $e))
            ->orderBy('s.date_prevue')->orderBy('l.nom_lot')->paginate(50)->withQueryString();

        return view('volailles.soins.index', [
            'retards' => $retards, 'dujour' => $dujour, 'periode' => $periode, 'filtres' => $f, 'du' => $du, 'au' => $au,
            'lots' => DB::table('modLotVol_lots_volailles')->where('statut', 'actif')->orderBy('nom_lot')->get(),
            'catalogue' => DB::table('modSoinVacc_catalogue')->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'id_lot' => ['required', 'integer'], 'id_soin' => ['required', 'integer'],
            'date_prevue' => ['required', 'date'], 'observation' => ['nullable', 'string', 'max:2000'],
        ], [], ['id_lot' => 'lot', 'id_soin' => 'soin', 'date_prevue' => 'date prévue']);
        Soins::ajouterOccasionnel((int) $d['id_lot'], (int) $d['id_soin'], $d['date_prevue'], $d['observation'] ?? null);

        return back()->with('succes', 'Soin planifié.');
    }

    public function etat(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate([
            'etat' => ['required', Rule::in(['realise', 'annule'])], 'commentaire' => ['nullable', 'string', 'max:4000'],
            'empreinte' => ['required', 'string'], 'lot_filtre' => ['nullable', 'integer'],
        ]);
        // Réaliser un soin : droit « réaliser » ; l'annuler : droit de gestion des soins.
        abort_unless($request->user()->can($d['etat'] === 'realise' ? 'SAV.realiser' : 'SAV.gerer'), 403);
        Soins::changerEtat($id, $d['etat'], $d['commentaire'] ?? null, $d['empreinte'], isset($d['lot_filtre']) ? (int) $d['lot_filtre'] : null);

        return back()->with('succes', $d['etat'] === 'realise' ? 'Soin marqué réalisé.' : 'Soin annulé.');
    }

    public function catalogue(Request $request): View
    {
        return view('volailles.soins.catalogue', [
            'catalogue' => DB::table('modSoinVacc_catalogue')->orderBy('type')->orderBy('nom')->get(),
            'edition' => $request->integer('modifier') ?: null,
        ]);
    }

    public function enregistrerCatalogue(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate([
            'nom' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['soin', 'vaccin'])],
            'produit_utilise' => ['nullable', 'string', 'max:100'], 'dose' => ['nullable', 'string', 'max:50'],
            'voie_administration' => ['nullable', 'string', 'max:50'], 'espece_cible' => ['nullable', 'string', 'max:50'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ], [], ['produit_utilise' => 'produit', 'voie_administration' => 'voie', 'espece_cible' => 'espèce cible']);
        Soins::enregistrerCatalogue($id, $d);

        return redirect()->route('soins.catalogue')->with('succes', 'Catalogue mis à jour.');
    }

    public function programme(): View
    {
        return view('volailles.soins.programme', [
            'lignes' => DB::table('modSoinVacc_programme_poussins as p')->leftJoin('modSoinVacc_catalogue as c', 'c.id', '=', 'p.id_soin')
                ->orderBy('p.espece')->orderBy('p.jour_post_eclosion')->get(['p.*', 'c.nom', 'c.type', 'c.produit_utilise']),
            'catalogue' => DB::table('modSoinVacc_catalogue')->orderBy('nom')->get(),
            'plans' => DB::table('modProphylaxie_plans')->orderBy('espece')->orderBy('nom_plan')->get(),
        ]);
    }

    public function ajouterProgramme(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'espece' => ['required', 'string', 'max:50'], 'jour_post_eclosion' => ['required', 'integer', 'min:0', 'max:3650'],
            'id_soin' => ['required', 'integer', 'exists:modSoinVacc_catalogue,id'], 'commentaire' => ['nullable', 'string', 'max:1000'],
        ], [], ['jour_post_eclosion' => 'jour', 'id_soin' => 'soin']);
        $d['espece'] = mb_strtolower(trim($d['espece']));
        $id = DB::table('modSoinVacc_programme_poussins')->insertGetId($d);
        Journal::enregistrer('programme_poussins.ajout', 'SAV', 'programme_poussins', $id, apres: $d);

        return back()->with('succes', 'Étape ajoutée au programme.');
    }

    public function supprimerProgramme(int $id): RedirectResponse
    {
        $l = DB::table('modSoinVacc_programme_poussins')->where('id', $id)->first() ?? abort(404);
        DB::table('modSoinVacc_programme_poussins')->where('id', $id)->delete();
        Journal::enregistrer('programme_poussins.suppression', 'SAV', 'programme_poussins', $id, (array) $l);

        return back()->with('succes', 'Étape retirée du programme. Les soins déjà planifiés ne sont pas modifiés.');
    }
}

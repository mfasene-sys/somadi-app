<?php

namespace App\Http\Controllers\Ovins;

use App\Domaine\Ovins\Referentiels;
use App\Domaine\Ovins\Reproduction;
use App\Domaine\Ovins\Soins;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferentielController extends Controller
{
    public function lots(): View
    {
        return view('ovins.referentiels.lots', ['lots' => Referentiels::ecartsLots(), 'sites' => Referentiels::sites(true)]);
    }

    public function sites(): View
    {
        $presents = DB::table('OVINS_animaux')->where('statut', 'en_ferme')->whereNull('date_sortie')->groupBy('id_site')->pluck(DB::raw('COUNT(*)'), 'id_site');

        return view('ovins.referentiels.sites', ['sites' => Referentiels::sites(), 'presents' => $presents]);
    }

    public function proprietaires(): View
    {
        $presents = DB::table('OVINS_animaux')->where('statut', 'en_ferme')->whereNull('date_sortie')->groupBy('id_proprietaire')->pluck(DB::raw('COUNT(*)'), 'id_proprietaire');

        return view('ovins.referentiels.proprietaires', ['proprietaires' => Referentiels::proprietaires(), 'presents' => $presents]);
    }

    public function enregistrerSite(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate(['code_site' => ['required', 'string', 'max:50'], 'nom_site' => ['required', 'string', 'max:150'], 'localisation' => ['nullable', 'string', 'max:255'],
            'capacite_ovins' => ['nullable', 'integer', 'min:0'], 'type_site' => ['nullable', 'string', 'max:50'], 'actif' => ['nullable', 'boolean'], 'observations' => ['nullable', 'string', 'max:2000']]);
        Referentiels::enregistrerSite($id, $d, $request->input('empreinte'));

        return redirect()->route('ovins.sites')->with('succes', $id ? 'Site mis à jour.' : 'Site créé.');
    }

    public function enregistrerProprietaire(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate(['nom_proprietaire' => ['required', 'string', 'max:120'], 'telephone' => ['nullable', 'string', 'max:30'], 'couleur_hex' => ['nullable', 'string', 'max:7'],
            'actif' => ['nullable', 'boolean'], 'observations' => ['nullable', 'string', 'max:2000']]);
        Referentiels::enregistrerProprietaire($id, $d, $request->input('empreinte'));

        return redirect()->route('ovins.proprietaires')->with('succes', $id ? 'Propriétaire mis à jour.' : 'Propriétaire créé.');
    }

    private function validerLot(Request $request, bool $creation): array
    {
        return $request->validate([
            'nom_lot' => ['required', 'string', 'max:150'], 'type_lot' => ['required', Rule::in(array_keys(config('somadi.ovins.types_lot')))], 'race' => ['nullable', 'string', 'max:100'],
            'date_entree' => ['required', 'date', 'before_or_equal:today'], 'provenance' => ['nullable', 'string', 'max:150'],
            'valeur_unitaire_achat' => ['nullable', 'string', 'max:20'], 'valeur_totale_achat' => ['nullable', 'string', 'max:20'], 'observations' => ['nullable', 'string', 'max:2000'],
        ] + ($creation ? ['id_site' => ['required', 'integer'], 'effectif_initial' => ['required', 'integer', 'min:0', 'max:100000']] : ['statut' => ['required', Rule::in(['actif', 'clos'])]]),
            [], ['nom_lot' => 'nom', 'type_lot' => 'type', 'date_entree' => 'date d’entrée', 'id_site' => 'site']);
    }

    public function creerLot(): View
    {
        return view('ovins.referentiels.lot-form', ['lot' => null, 'sites' => Referentiels::sites(true)]);
    }

    public function storeLot(Request $request): RedirectResponse
    {
        $d = $this->validerLot($request, true);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Referentiels::creerLot($d);
        });

        return redirect()->route('ovins.lots.show', $id)->with('succes', 'Lot créé.');
    }

    public function lot(int $id): View
    {
        $lot = DB::table('OVINS_lots as l')->join('OVINS_sites as s', 's.id_site', '=', 'l.id_site')->where('l.id_lot', $id)->first(['l.*', 's.nom_site']) ?? abort(404);

        return view('ovins.referentiels.lot', [
            'lot' => $lot,
            'animaux' => DB::table('OVINS_animaux')->where('id_lot', $id)->where('statut', 'en_ferme')->whereNull('date_sortie')->orderBy('code_animal')->get(),
            'mouvements' => DB::table('OVINS_mouvements as m')->leftJoin('OVINS_animaux as a', 'a.id_animal', '=', 'm.id_animal')->where('m.id_lot', $id)
                ->orderByDesc('m.date_mvt')->orderByDesc('m.id_mvt')->limit(100)->get(['m.*', 'a.code_animal']),
            'soins' => Soins::requete(['lot' => $id])->where('r.statut', 'planifie')->orderBy('r.date_soin')->limit(30)->get(),
            'modeles' => DB::table('OVINS_modeles_soins')->where('actif', 1)->whereIn('cible', ['tous', 'lot'])->orderBy('nom_modele')->get(),
            'cycles' => DB::table('OVINS_lot_cycles')->where('id_lot', $id)->count(),
        ]);
    }

    public function editLot(int $id): View
    {
        return view('ovins.referentiels.lot-form', ['lot' => DB::table('OVINS_lots')->where('id_lot', $id)->first() ?? abort(404), 'sites' => Referentiels::sites()]);
    }

    public function updateLot(Request $request, int $id): RedirectResponse
    {
        Referentiels::modifierLot($id, $this->validerLot($request, false), (string) $request->input('empreinte'));

        return redirect()->route('ovins.lots.show', $id)->with('succes', 'Lot mis à jour.');
    }

    public function ajuster(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['effectif' => ['required', 'integer', 'min:0', 'max:100000'], 'motif' => ['required', 'string', 'max:500'], 'date' => ['required', 'date', 'before_or_equal:today']]);
        Referentiels::ajusterEffectif($id, (int) $d['effectif'], $d['motif'], $d['date']);

        return back()->with('succes', 'Effectif corrigé ; la correction figure dans les mouvements du lot.');
    }

    public function calendrier(int $id): View
    {
        $lot = DB::table('OVINS_lots as l')->join('OVINS_sites as s', 's.id_site', '=', 'l.id_site')->where('l.id_lot', $id)->first(['l.*', 's.nom_site']) ?? abort(404);

        return view('ovins.reproduction.calendrier', ['lot' => $lot, 'p' => config('somadi.ovins.calendrier'),
            'types' => DB::table('OVINS_soins_types')->orderBy('nom_soin')->get()] + Reproduction::calendrier($id));
    }

    public function genererCalendrier(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['premiere_lutte' => ['required', 'date'], 'gestation' => ['required', 'integer'], 'allaitement' => ['required', 'integer'],
            'preparation' => ['required', 'integer'], 'cycle_mois' => ['required', 'integer'], 'horizon_ans' => ['required', 'integer']]);
        $n = Reproduction::genererCalendrier($id, $d['premiere_lutte'], $d);

        return back()->with('succes', "$n cycle(s) générés. Ce calendrier est théorique : il ne crée aucune naissance.");
    }

    public function ajouterAction(Request $request, int $plan): RedirectResponse
    {
        $d = $request->validate(['date_soin' => ['required', 'date'], 'libelle_action' => ['nullable', 'string', 'max:255'], 'id_type_soin' => ['nullable', 'integer'],
            'dose' => ['nullable', 'string', 'max:30'], 'commentaire' => ['nullable', 'string', 'max:1000']]);
        Reproduction::ajouterAction($plan, $d);

        return back()->with('succes', 'Action ajoutée'.(! empty($d['id_type_soin']) ? ' et soin planifié pour le lot.' : '.'));
    }
}

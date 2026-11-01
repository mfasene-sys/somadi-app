<?php

namespace App\Http\Controllers\Ovins;

use App\Domaine\Ovins\Mouvements;
use App\Domaine\Ovins\Referentiels;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MouvementController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['type' => ['nullable', 'string', 'max:30'], 'site' => ['nullable', 'integer'], 'annee' => ['nullable', 'integer', 'min:2000', 'max:2099']]);
        $annee = (int) ($f['annee'] ?? today()->year);
        $mouvements = DB::table('OVINS_mouvements as m')->leftJoin('OVINS_animaux as a', 'a.id_animal', '=', 'm.id_animal')->leftJoin('OVINS_lots as l', 'l.id_lot', '=', 'm.id_lot')
            ->whereYear('m.date_mvt', $annee)
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('m.type_mvt', $t))
            ->when($f['site'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('m.id_site_source', $s)->orWhere('m.id_site_destination', $s)))
            ->orderByDesc('m.date_mvt')->orderByDesc('m.id_mvt')->paginate(50, ['m.*', 'a.code_animal', 'a.nom_animal', 'l.nom_lot'])->withQueryString();
        // Synthèses par site et par propriétaire (animaux en ferme ; sorties de l'année).
        $parSite = DB::table('OVINS_animaux as a')->join('OVINS_sites as s', 's.id_site', '=', 'a.id_site')->where('a.statut', 'en_ferme')->whereNull('a.date_sortie')
            ->groupBy('s.id_site', 's.nom_site')->orderBy('s.nom_site')->get(['s.nom_site', DB::raw('COUNT(*) n'), DB::raw("SUM(a.sexe='F') f"), DB::raw("SUM(a.sexe='M') m")]);
        $parProprio = DB::table('OVINS_animaux as a')->leftJoin('OVINS_proprietaires as p', 'p.id_proprietaire', '=', 'a.id_proprietaire')->where('a.statut', 'en_ferme')->whereNull('a.date_sortie')
            ->groupBy('p.id_proprietaire', 'p.nom_proprietaire', 'p.couleur_hex')->orderBy('p.nom_proprietaire')->get(['p.nom_proprietaire', 'p.couleur_hex', DB::raw('COUNT(*) n')]);
        $sorties = DB::table('OVINS_sorties')->whereYear('date_sortie', $annee)->groupBy('type_sortie')->get(['type_sortie', DB::raw('COUNT(*) n'), DB::raw('SUM(montant_total) total')]);

        return view('ovins.mouvements.index', compact('mouvements', 'f', 'annee', 'parSite', 'parProprio', 'sorties') + [
            'types' => DB::table('OVINS_mouvements')->distinct()->orderBy('type_mvt')->pluck('type_mvt'), 'sites' => Referentiels::sites(),
            'nonLiees' => Mouvements::sortiesNonLiees()->count(),
        ]);
    }

    public function transferer(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['id_lot' => ['required', 'integer'], 'date' => ['required', 'date'], 'integration' => ['nullable', 'boolean'], 'id_modele' => ['nullable', 'integer'],
            'commentaire' => ['nullable', 'string', 'max:500']], [], ['id_lot' => 'lot de destination']);
        Mouvements::transferer($id, (int) $d['id_lot'], $d['date'], $request->boolean('integration'), ($d['id_modele'] ?? null) ? (int) $d['id_modele'] : null, $d['commentaire'] ?? '');

        return redirect()->route('ovins.animaux.show', $id)->with('succes', $request->boolean('integration') ? 'Animal intégré en reproduction.' : 'Transfert enregistré.');
    }

    public function sortir(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['type_sortie' => ['required', Rule::in(array_keys(config('somadi.ovins.sorties')))], 'date_sortie' => ['required', 'date'],
            'prix' => ['nullable', 'string', 'max:20'], 'client_nom' => ['nullable', 'string', 'max:100'], 'client_contact' => ['nullable', 'string', 'max:20'],
            'motif' => ['nullable', 'string', 'max:100'], 'commentaire' => ['nullable', 'string', 'max:2000']], [], ['client_contact' => 'téléphone du client']);
        abort_if($d['type_sortie'] === 'vente' && ! $request->user()->can('VEN.gerer'), 403);
        $r = DB::transaction(function () use ($request, $d, $id) {
            Requete::consommer($request);

            return Mouvements::sortir($id, $d);
        });

        return $r['vente']
            ? redirect()->route('ventes.show', $r['vente'])->with('succes', 'Vente enregistrée : la créance est créée, encaissez-la quand le client paie.')
            : redirect()->route('ovins.animaux.show', $id)->with('succes', 'Sortie enregistrée.');
    }

    // ----- Rapprochement historique (admin)

    public function rapprochements(): View
    {
        return view('ovins.mouvements.rapprochements', ['sorties' => Mouvements::sortiesNonLiees()]);
    }

    public function rapprochement(int $sortie): View
    {
        $s = DB::table('OVINS_sorties as s')->leftJoin('OVINS_animaux as a', 'a.id_animal', '=', 's.id_animal')->where('s.id_sortie', $sortie)->first(['s.*', 'a.code_animal', 'a.nom_animal']) ?? abort(404);

        return view('ovins.mouvements.rapprochement', ['s' => $s, 'concordantes' => Mouvements::ventesConcordantes($s), 'recettes' => Mouvements::recettesAdmissibles($s),
            'plan' => session("ovins.rappro.$sortie"), 'liaison' => DB::table('modVente_ovins_liaisons')->where('id_sortie', $sortie)->first()]);
    }

    private function donnees(Request $request): array
    {
        return $request->validate(['mode' => ['required', Rule::in(['existante', 'creation'])], 'id_vente' => ['nullable', 'integer'], 'recettes' => ['nullable', 'array', 'max:30'],
            'recettes.*' => ['integer'], 'aucun_paiement' => ['nullable', 'boolean'], 'motif' => ['required', 'string', 'max:2000'], 'client_tel' => ['nullable', 'string', 'max:20']]);
    }

    public function apercu(Request $request, int $sortie): RedirectResponse
    {
        $d = $this->donnees($request);
        $p = Mouvements::plan($sortie, $d);
        session(["ovins.rappro.$sortie" => ['d' => $d, 'p' => $p]]);

        return redirect()->route('ovins.rapprochements.show', $sortie);
    }

    public function confirmer(Request $request, int $sortie): RedirectResponse
    {
        $request->validate(['confirmation' => ['accepted']], ['confirmation.accepted' => 'Cochez la case de confirmation.']);
        $s = session("ovins.rappro.$sortie") ?? abort(409, 'Aperçu expiré : recommencez.');
        $vente = Mouvements::rapprocher($sortie, $s['d'], $s['p']['empreinte']);
        session()->forget("ovins.rappro.$sortie");

        return redirect()->route('ventes.show', $vente)->with('succes', 'Sortie rapprochée de la vente et liaison journalisée.');
    }
}

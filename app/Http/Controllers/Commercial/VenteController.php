<?php

namespace App\Http\Controllers\Commercial;

use App\Domaine\Tresorerie;
use App\Domaine\Ventes;
use App\Http\Controllers\Controller;
use App\Support\Lieux;
use App\Support\Montant;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VenteController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'annee' => ['nullable', 'integer', 'min:1900', 'max:2099'], 'q' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', Rule::in(['A encaisser', 'Encaissé'])], 'produit' => ['nullable', 'string', 'max:100'],
        ]);
        $annee = (int) ($f['annee'] ?? today()->year);
        $q = DB::table('modVente_Ventes')->whereYear('date_vente', $annee)
            ->when($f['q'] ?? null, fn ($x, $t) => $x->where(fn ($w) => $w->where('client_nom', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $t).'%')->orWhere('client_tel', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $t).'%')))
            ->when($f['statut'] ?? null, fn ($x, $s) => $x->where('statut', $s))
            ->when($f['produit'] ?? null, fn ($x, $p) => $x->where('espece', $p));
        $totaux = (clone $q)->select(DB::raw('COUNT(*) n'), DB::raw('COALESCE(SUM(montant_total),0) total'), DB::raw('COALESCE(SUM(montant_reste_encaisser),0) reste'))->first();

        return view('commercial.ventes.index', [
            'ventes' => $q->orderByDesc('date_vente')->orderByDesc('id')->paginate(30)->withQueryString(),
            'annee' => $annee, 'filtres' => $f, 'totaux' => $totaux,
            'annees' => DB::table('modVente_Ventes')->selectRaw('DISTINCT YEAR(date_vente) a')->orderByDesc('a')->pluck('a')->filter()->push(today()->year)->unique()->sortDesc()->values(),
            'aCompleter' => DB::table('modVente_Ventes_A_Completer')->whereRaw("LOWER(TRIM(statut)) = 'attente'")->count(),
        ]);
    }

    public function create(): View
    {
        $lots = DB::table('modLotVol_lots_volailles')->where('statut', 'actif')->where('effectif_en_cours', '>', 0)->orderBy('nom_lot')->get();

        return view('commercial.ventes.form', ['lots' => $lots, 'produits' => collect(Ventes::produits())->filter(fn ($p) => $p['general'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->valider($request) + $request->validate([
            'produit' => ['required', 'string', 'max:100'], 'quantite' => ['required', 'integer', 'min:1', 'max:1000000'],
            'id_lot' => ['nullable', 'integer'], 'origine' => ['nullable', 'integer', 'min:0'],
        ]);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Ventes::creer($d);
        });

        return redirect()->route('ventes.show', $id)->with('succes', 'Vente enregistrée.');
    }

    public function oeufs(Request $request): View
    {
        $produit = $request->query('produit', 'oeufs_table');
        $produit = in_array($produit, ['oeufs_feconds', 'oeufs_table', 'oeufs_caille'], true) ? $produit : 'oeufs_table';
        $espece = Ventes::produits()[$produit]['espece'];
        $lots = DB::table('lots_oeufs_ramasses as l')->join('bandes_parentales as b', 'b.id', '=', 'l.id_bande')
            ->leftJoin('stock_oeufs_ramasses as s', 's.id', '=', 'l.id_stock_oeufs')
            ->where('l.type_lot', 'vendre')->where('l.statut', 'nouveau')->where('b.espece', $espece)
            ->orderBy('s.date_ramassage')->orderBy('l.id')->get(['l.*', 'b.nom_bande', 's.date_ramassage'])
            ->map(function ($l) {
                $l->dispo = Ventes::disponibleOeufs($l);

                return $l;
            })->filter(fn ($l) => $l->dispo['disponible'] > 0 || ! $l->dispo['coherent']);

        return view('commercial.ventes.oeufs', ['lots' => $lots, 'produit' => $produit]);
    }

    public function vendreOeufs(Request $request): RedirectResponse
    {
        $d = $this->valider($request) + $request->validate([
            'produit' => ['required', Rule::in(['oeufs_feconds', 'oeufs_table', 'oeufs_caille'])], 'quantite' => ['required', 'integer', 'min:1'],
            'lots' => ['required', 'array', 'min:1', 'max:50'], 'lots.*' => ['integer', 'distinct'],
        ], ['lots.required' => 'Sélectionnez au moins un lot d’œufs.']);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Ventes::vendreOeufs($d);
        });

        return redirect()->route('ventes.show', $id)->with('succes', 'Vente d’œufs enregistrée.');
    }

    public function aCompleter(): View
    {
        return view('commercial.ventes.a-completer', [
            'sorties' => DB::table('modVente_Ventes_A_Completer as s')->leftJoin('modLotVol_lots_volailles as l', 'l.id', '=', 's.id_lot')
                ->whereRaw("LOWER(TRIM(s.statut)) = 'attente'")->orderBy('s.date_vente')->get(['s.*', 'l.espece', 'l.race']),
            'produits' => collect(Ventes::produits())->filter(fn ($p) => $p['general']),
        ]);
    }

    public function finaliser(Request $request, int $id): RedirectResponse
    {
        $d = $this->valider($request, false) + $request->validate(['produit' => ['required', 'string'], 'quantite' => ['required', 'integer', 'min:1']]);
        $vente = Ventes::finaliser($id, $d);

        return redirect()->route('ventes.show', $vente)->with('succes', 'Vente finalisée (l’effectif avait déjà été débité à la sortie).');
    }

    public function show(int $id): View
    {
        $v = DB::table('modVente_Ventes')->where('id', $id)->first() ?? abort(404);

        return view('commercial.ventes.show', [
            'v' => $v,
            'audit' => Tresorerie::audit($v),
            'operations' => DB::table('modCompta_operations')->whereRaw("LOWER(TRIM(source_table)) = 'modvente_ventes'")->where('source_id', $id)->orderBy('date_op')->orderBy('id')->get(),
            'affectations' => DB::table('modVente_oeufs_affectations as a')->join('lots_oeufs_ramasses as l', 'l.id', '=', 'a.id_lot_oeufs')
                ->leftJoin('stock_oeufs_ramasses as s', 's.id', '=', 'l.id_stock_oeufs')->where('a.id_vente', $id)->get(['a.*', 'l.quantite as quantite_lot', 's.date_ramassage']),
            'lot' => $v->id_lot ? DB::table('modLotVol_lots_volailles')->where('id', $v->id_lot)->first() : null,
            'borne' => \App\Domaine\Exercices::borneCloture(),
        ]);
    }

    public function encaisser(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate([
            'mode' => ['required', Rule::in(['total', 'partiel'])], 'montant' => ['nullable', 'required_if:mode,partiel', 'string', 'max:20'],
            'date_op' => ['required', 'date', 'before_or_equal:today'], 'moyen_paiement' => ['required', Rule::in(Tresorerie::moyens())],
            'categorie' => ['nullable', Rule::in(Tresorerie::categories('recette'))], 'observations' => ['nullable', 'string', 'max:1000'],
        ], [], ['date_op' => 'date du paiement', 'moyen_paiement' => 'moyen de paiement']);
        DB::transaction(function () use ($request, $id, $d) {
            Requete::consommer($request);
            Tresorerie::encaisser($id, $d);
        });

        return redirect()->route('ventes.show', $id)->with('succes', 'Encaissement enregistré.');
    }

    private function valider(Request $request, bool $avecDate = true): array
    {
        $regles = [
            'client_nom' => ['required', 'string', 'max:100'], 'client_tel' => ['required', 'string', 'max:20'],
            'prix_unitaire' => ['required', 'string', 'max:20'], 'commentaire' => ['nullable', 'string', 'max:2000'],
            'encaissement' => ['nullable', Rule::in(['aucun', 'total', 'partiel'])], 'montant_encaisse' => ['nullable', 'required_if:encaissement,partiel', 'string', 'max:20'],
            'moyen_paiement' => ['nullable', 'required_if:encaissement,total,partiel', Rule::in(Tresorerie::moyens())],
        ];
        if ($avecDate) {
            $regles['date_vente'] = ['required', 'date', 'before_or_equal:today'];
        }

        return $request->validate($regles, [], [
            'client_nom' => 'client', 'client_tel' => 'téléphone', 'prix_unitaire' => 'prix unitaire', 'date_vente' => 'date de vente',
            'montant_encaisse' => 'montant encaissé', 'moyen_paiement' => 'moyen de paiement',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Commercial;

use App\Domaine\Tresorerie;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TresorerieController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'annee' => ['nullable', 'integer', 'min:1900', 'max:2099'], 'mois' => ['nullable', 'integer', 'min:1', 'max:12'],
            'type' => ['nullable', Rule::in(['recette', 'depense'])], 'categorie' => ['nullable', 'string', 'max:100'],
            'invalides' => ['nullable', 'boolean'],
        ]);
        $annee = (int) ($f['annee'] ?? today()->year);
        $q = DB::table('modCompta_operations')->whereYear('date_op', $annee)
            ->when($f['mois'] ?? null, fn ($x, $m) => $x->whereMonth('date_op', $m))
            ->when($f['type'] ?? null, fn ($x, $t) => $x->where('type_op', $t))
            ->when($f['categorie'] ?? null, fn ($x, $c) => $x->where('categorie', $c))
            ->when(empty($f['invalides']), fn ($x) => $x->where('est_valide', 1));
        $totaux = (clone $q)->where('est_valide', 1)->groupBy('type_op')->select('type_op', DB::raw('SUM(montant) t'))->pluck('t', 'type_op');

        return view('commercial.tresorerie.index', [
            'operations' => $q->orderByDesc('date_op')->orderByDesc('id')->paginate(40)->withQueryString(),
            'annee' => $annee, 'filtres' => $f, 'totaux' => $totaux,
            'categories' => config('somadi.categories'), 'moyens' => Tresorerie::moyens(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'type_op' => ['required', Rule::in(['recette', 'depense'])], 'date_op' => ['required', 'date', 'before_or_equal:today'],
            'categorie' => ['required', 'string', 'max:100'], 'montant' => ['required', 'string', 'max:20'],
            'libelle' => ['required', 'string', 'max:255'], 'moyen_paiement' => ['required', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ], [], ['type_op' => 'type', 'date_op' => 'date', 'libelle' => 'libellé', 'moyen_paiement' => 'moyen de paiement']);
        DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);
            Tresorerie::creerOperation($d);
        });

        return back()->with('succes', ($d['type_op'] === 'recette' ? 'Recette' : 'Dépense').' enregistrée.');
    }

    public function invalider(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:1000'], 'confirmation' => ['accepted']],
            ['confirmation.accepted' => 'Cochez la case de confirmation.'], ['motif' => 'motif']);
        Tresorerie::invalider($id, $d['motif']);

        return back()->with('succes', 'Opération invalidée. Elle reste visible dans le journal mais n’est plus comptée.');
    }

    public function recapitulatif(Request $request): View
    {
        $f = $request->validate(['annee' => ['nullable', 'integer', 'min:1900', 'max:2099']]);
        $annee = (int) ($f['annee'] ?? today()->year);

        return view('commercial.tresorerie.recapitulatif', ['annee' => $annee, 'r' => Tresorerie::recapitulatif($annee)]);
    }

    public function aEncaisser(): View
    {
        return view('commercial.tresorerie.a-encaisser', [
            'ventes' => DB::table('modVente_Ventes')->where('montant_reste_encaisser', '>', 0)->orderBy('date_vente')->paginate(40),
            'total' => DB::table('modVente_Ventes')->sum('montant_reste_encaisser'),
        ]);
    }
}

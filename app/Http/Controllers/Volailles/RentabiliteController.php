<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\LotsVolailles;
use App\Domaine\Pesees;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RentabiliteController extends Controller
{
    /** Liste des lots actifs non exclus des analyses (EC10), avec accès à leur fiche de rentabilité. */
    public function index(): View
    {
        $lots = DB::table('modLotVol_lots_volailles as l')->leftJoin('app_lots_options as o', 'o.id_lot', '=', 'l.id')
            ->where('l.statut', 'actif')->orderBy('l.nom_lot')->get(['l.*', 'o.exclu_analyses', 'o.motif_exclusion']);

        return view('volailles.rentabilite.index', ['lots' => $lots]);
    }

    public function show(Request $request, int $id): View
    {
        $lot = DB::table('modLotVol_lots_volailles')->where('id', $id)->first() ?? abort(404);
        $h = (int) $request->query('hypothese', 0);
        $h = in_array($h, Pesees::HYPOTHESES, true) ? $h : 0;

        return view('volailles.rentabilite.show', [
            'lot' => $lot,
            'age' => LotsVolailles::age($lot),
            'hypothese' => $h,
            'r' => Pesees::rentabilite($lot, $h),
            'option' => DB::table('app_lots_options')->where('id_lot', $id)->first(),
        ]);
    }

    public function exclusion(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['exclu' => ['required', 'boolean'], 'motif' => ['nullable', 'string', 'max:255']]);
        LotsVolailles::exclureDesAnalyses($id, (bool) $d['exclu'], $d['motif'] ?? null);

        return back()->with('succes', $d['exclu'] ? 'Lot exclu des analyses.' : 'Lot réintégré dans les analyses.');
    }

    public function references(): View
    {
        return view('volailles.rentabilite.references', ['refs' => DB::table('modLot_Vol_ref_cout_age_poulet')->orderBy('espece')->orderBy('age_jours')->get()]);
    }

    public function enregistrerReference(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'espece' => ['required', 'string', 'max:30'],
            'age_jours' => ['required', 'integer', 'min:0', 'max:3650'],
            'cout_cumule_par_tete' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'marge_pct' => ['required', 'numeric', 'min:0', 'max:999'],
            'actif' => ['nullable', 'boolean'],
            'commentaire' => ['nullable', 'string', 'max:255'],
        ], [], ['age_jours' => 'âge', 'cout_cumule_par_tete' => 'coût cumulé', 'marge_pct' => 'marge']);
        Pesees::enregistrerReference($d);

        return back()->with('succes', 'Référence enregistrée.');
    }
}

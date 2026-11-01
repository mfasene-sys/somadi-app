<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\StockOeufs;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OeufsController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['type' => ['nullable', Rule::in(['incuber', 'vendre'])], 'statut' => ['nullable', Rule::in(['nouveau', 'charge', 'vendu'])]]);
        $stocks = DB::table('stock_oeufs_ramasses as s')->leftJoin('bandes_parentales as b', 'b.id', '=', 's.id_bande')
            ->where('s.statut', 'attente')->where('s.total_oeufs', '>', 0)
            ->orderBy('s.date_ramassage')->get(['s.*', 'b.nom_bande', 'b.espece'])
            ->map(function ($s) {
                $s->audit = StockOeufs::audit($s);

                return $s;
            });
        $lots = DB::table('lots_oeufs_ramasses as l')->leftJoin('bandes_parentales as b', 'b.id', '=', 'l.id_bande')
            ->leftJoin('stock_oeufs_ramasses as s', 's.id', '=', 'l.id_stock_oeufs')
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('l.type_lot', $t))
            ->when($f['statut'] ?? 'nouveau', fn ($q, $s) => $q->where('l.statut', $s))
            ->orderByDesc('l.id')->paginate(30, ['l.*', 'b.nom_bande', 's.date_ramassage'])->withQueryString();
        $totaux = DB::table('lots_oeufs_ramasses')->where('statut', 'nouveau')->groupBy('type_lot')->select('type_lot', DB::raw('SUM(quantite) q'))->pluck('q', 'type_lot');

        return view('volailles.oeufs.index', ['stocks' => $stocks, 'lots' => $lots, 'filtres' => $f + ['statut' => 'nouveau'], 'totaux' => $totaux]);
    }

    public function affecter(Request $request, int $stock): RedirectResponse
    {
        $d = $request->validate([
            'type_lot' => ['required', Rule::in(array_keys(StockOeufs::TYPES))],
            'quantite' => ['required', 'integer', 'min:1'],
            'empreinte' => ['required', 'string'],
        ], [], ['type_lot' => 'destination', 'quantite' => 'quantité']);
        StockOeufs::affecter($stock, $d['type_lot'], (int) $d['quantite'], $d['empreinte']);

        return back()->with('succes', $d['quantite'].' œufs affectés « '.StockOeufs::TYPES[$d['type_lot']].' ».');
    }
}

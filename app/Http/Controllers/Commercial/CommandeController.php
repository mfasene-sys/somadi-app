<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** EC04 : registres historiques de commandes, en lecture seule. */
class CommandeController extends Controller
{
    public function index(): View
    {
        $paiements = DB::table('paiements_commandes')->groupBy('id_commande')->select('id_commande', DB::raw('SUM(montant) paye'), DB::raw('COUNT(*) n'));

        return view('commercial.commandes.index', [
            'commandesVente' => DB::table('commandes_vente as c')->leftJoinSub($paiements, 'p', 'p.id_commande', '=', 'c.id')
                ->orderByDesc('c.date_reservation')->orderByDesc('c.id')->get(['c.*', 'p.paye', 'p.n']),
            'commandes' => DB::table('commandes')->orderByDesc('date_commande')->orderByDesc('id')->get(),
            'relances' => DB::table('relances_commandes')->orderByDesc('date_heure')->limit(50)->get(),
        ]);
    }
}

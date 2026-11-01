<?php

namespace App\Http\Controllers\Commercial;

use App\Domaine\Rapprochements;
use App\Domaine\Tresorerie;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RapprochementController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['annee' => ['nullable', 'integer', 'min:1900', 'max:2099'], 'q' => ['nullable', 'string', 'max:100'], 'tous' => ['nullable', 'boolean']]);
        $annee = (int) ($f['annee'] ?? today()->year);

        return view('commercial.rapprochements.index', [
            'annee' => $annee, 'filtres' => $f,
            'ventes' => Rapprochements::ventes($annee, $f['q'] ?? null, empty($f['tous']))->withQueryString(),
            'journal' => DB::table('modCompta_rapprochements')->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function show(int $id): View
    {
        $v = DB::table('modVente_Ventes')->where('id', $id)->first() ?? abort(404);

        return view('commercial.rapprochements.show', [
            'v' => $v, 'audit' => Tresorerie::audit($v),
            'liees' => Tresorerie::encaisse($id)['operations'],
            'candidates' => Rapprochements::candidates($v),
            'borne' => \App\Domaine\Exercices::borneCloture(),
            'historique' => DB::table('modCompta_rapprochements')->where('id_vente', $id)->orderByDesc('id')->get(),
        ]);
    }

    public function apercu(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['operations' => ['nullable', 'array', 'max:30'], 'operations.*' => ['integer', 'distinct'], 'motif' => ['required', 'string', 'max:2000']]);
        $jeton = Rapprochements::preparer($id, $d['operations'] ?? [], $d['motif']);

        return redirect()->route('rapprochements.apercu', $jeton);
    }

    public function voirApercu(string $jeton): View
    {
        $t = Rapprochements::ticket($jeton);

        return view('commercial.rapprochements.apercu', [
            't' => $t, 'jeton' => $jeton,
            'v' => DB::table('modVente_Ventes')->where('id', $t['vente'])->first(),
            'operations' => DB::table('modCompta_operations')->whereIn('id', $t['ids'] ?: [0])->get(),
        ]);
    }

    public function confirmer(Request $request, string $jeton): RedirectResponse
    {
        $t = Rapprochements::ticket($jeton);
        Rapprochements::confirmer($jeton, $request->boolean('confirmation'), $request->boolean('historique_complet'));

        return redirect()->route('rapprochements.show', $t['vente'])->with('succes', 'Rapprochement validé et journalisé.');
    }
}

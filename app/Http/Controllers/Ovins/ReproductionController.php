<?php

namespace App\Http\Controllers\Ovins;

use App\Domaine\Ovins\Referentiels;
use App\Domaine\Ovins\Reproduction;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReproductionController extends Controller
{
    public function index(): View
    {
        return view('ovins.reproduction.index', [
            'alertes' => Reproduction::alertes(),
            'agnelles' => Reproduction::agnellesPretes(),
            'recentes' => DB::table('OVINS_repro as r')->join('OVINS_animaux as m', 'm.id_animal', '=', 'r.id_mere')->leftJoin('OVINS_animaux as p', 'p.id_animal', '=', 'r.id_pere')
                ->orderByDesc(DB::raw('COALESCE(r.date_agnelage_reel, r.date_saillie)'))->orderByDesc('r.id_repro')->limit(30)
                ->get(['r.*', 'm.code_animal as code_mere', 'm.nom_animal as nom_mere', 'p.code_animal as code_pere']),
            'lotsRepro' => DB::table('OVINS_lots')->where('statut', 'actif')->whereIn('type_lot', ['reproductrices'])->orderBy('nom_lot')->get(),
            'modeles' => DB::table('OVINS_modeles_soins')->where('actif', 1)->whereIn('cible', ['tous', 'animal'])->orderBy('nom_modele')->get(),
        ]);
    }

    public function femelle(int $id): View
    {
        $a = DB::table('OVINS_animaux as a')->leftJoin('OVINS_lots as l', 'l.id_lot', '=', 'a.id_lot')->where('a.id_animal', $id)->first(['a.*', 'l.nom_lot']) ?? abort(404);
        abort_unless($a->sexe === 'F', 404);

        return view('ovins.reproduction.femelle', [
            'a' => $a, 'historique' => Reproduction::historique($id),
            'peres' => DB::table('OVINS_animaux')->where('sexe', 'M')->orderByRaw("statut = 'en_ferme' DESC")->orderBy('code_animal')->get(['id_animal', 'code_animal', 'nom_animal', 'statut']),
            'lots' => Referentiels::lots(true)->where('id_site', $a->id_site),
            'edition' => request()->integer('repro') ? DB::table('OVINS_repro')->where('id_repro', request()->integer('repro'))->where('id_mere', $id)->first() : null,
        ]);
    }

    public function enregistrer(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate([
            'id_mere' => ['required', 'integer'], 'id_pere' => ['nullable', 'integer'], 'date_saillie' => ['nullable', 'string', 'max:10'], 'type_saillie' => ['nullable', 'string', 'max:50'],
            'date_agnelage_prevu' => ['nullable', 'string', 'max:10'], 'date_agnelage_reel' => ['nullable', 'string', 'max:10'],
            'nb_agneaux_vivants' => ['nullable', 'string', 'max:3'], 'nb_agneaux_males' => ['nullable', 'string', 'max:3'], 'nb_agneaux_femelles' => ['nullable', 'string', 'max:3'],
            'nb_agneaux_morts' => ['nullable', 'string', 'max:3'], 'difficulte_agnelage' => ['nullable', 'boolean'], 'commentaire' => ['nullable', 'string', 'max:2000'],
            'id_lot_agneaux' => ['nullable', 'integer'],
        ]);
        $r = DB::transaction(function () use ($request, $d, $id) {
            Requete::consommer($request);

            return Reproduction::enregistrer($id, $d);
        });
        $msg = $r['agneaux'] ? count($r['agneaux']).' agneau(x) créé(s) avec leur filiation.' : 'Reproduction enregistrée.';

        return redirect()->route('ovins.reproduction.femelle', $d['id_mere'])->with('succes', $msg);
    }
}

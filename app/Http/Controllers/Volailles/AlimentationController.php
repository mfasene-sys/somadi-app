<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\Alimentation;
use App\Exceptions\RegleMetier;
use App\Http\Controllers\Controller;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AlimentationController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);
        $date = $f['date'] ?? today()->toDateString();
        $lots = DB::table('modLotVol_lots_volailles')->where('statut', 'actif')->orderBy('nom_lot')->get();
        $aliments = DB::table('modAlim_types')->orderBy('nom_aliment')->get()->keyBy('id');
        $distributions = DB::table('modAlim_distributions as d')->join('modLotVol_lots_volailles as l', 'l.id', '=', 'd.id_lot')
            ->where('d.date_distribution', $date)->orderBy('l.nom_lot')->get(['d.*', 'l.nom_lot']);
        $previsions = $lots->map(fn ($l) => ['lot' => $l, 'b' => Alimentation::besoins($l, $date)]);

        return view('volailles.alimentation.index', compact('date', 'lots', 'aliments', 'distributions', 'previsions'));
    }

    public function generer(Request $request): RedirectResponse
    {
        $d = $request->validate(['date' => ['required', 'date', 'before_or_equal:today']]);
        $r = Alimentation::generer($d['date']);
        $msg = "{$r['creees']} distribution(s) générée(s)".($r['deja'] ? ", {$r['deja']} déjà enregistrée(s) et laissée(s) telle(s) quelle(s)" : '').'.';

        return redirect()->route('alimentation.index', ['date' => $d['date']])->with('succes', $msg)->with('ignores', $r['ignores']);
    }

    public function distribuer(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'id_lot' => ['required', 'integer'], 'id_type_aliment' => ['required', 'integer'],
            'date_distribution' => ['required', 'date', 'before_or_equal:today'], 'quantite_kg' => ['required', 'string', 'max:20'],
            'remarque' => ['nullable', 'string', 'max:500'],
        ], [], ['id_lot' => 'lot', 'id_type_aliment' => 'aliment', 'quantite_kg' => 'quantité']);
        Alimentation::distribuer($d + ['remarque' => null]);

        return redirect()->route('alimentation.index', ['date' => $d['date_distribution']])->with('succes', 'Distribution enregistrée.');
    }

    public function corriger(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['quantite_kg' => ['required', 'string', 'max:20'], 'motif' => ['required', 'string', 'max:500']]);
        Alimentation::corriger($id, $d['quantite_kg'], $d['motif']);

        return back()->with('succes', 'Distribution corrigée.');
    }

    public function stocks(): View
    {
        return view('volailles.alimentation.stocks', [
            'stocks' => Alimentation::stocks(),
            'entrees' => DB::table('modAlim_stocks as s')->join('modAlim_types as t', 't.id', '=', 's.id_type')->orderByDesc('s.date_entree')->orderByDesc('s.id')->limit(50)->get(['s.*', 't.nom_aliment']),
        ]);
    }

    public function entreeStock(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'id_type' => ['required', 'integer', 'exists:modAlim_types,id'], 'date_entree' => ['required', 'date', 'before_or_equal:today'],
            'quantite_kg' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,2'], 'seuil_alerte' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ], [], ['id_type' => 'aliment', 'quantite_kg' => 'quantité', 'seuil_alerte' => 'seuil d’alerte']);
        $id = DB::table('modAlim_stocks')->insertGetId($d);
        Journal::enregistrer('alimentation.entree_stock', 'ALI', 'stock_aliment', $id, apres: $d);

        return back()->with('succes', 'Entrée de stock enregistrée.');
    }

    public function aliment(Request $request): RedirectResponse
    {
        $d = $request->validate(['nom_aliment' => ['required', 'string', 'max:100', 'unique:modAlim_types,nom_aliment']], [], ['nom_aliment' => 'nom de l’aliment']);
        $id = DB::table('modAlim_types')->insertGetId($d);
        Journal::enregistrer('alimentation.aliment', 'ALI', 'aliment', $id, apres: $d);

        return back()->with('succes', 'Aliment ajouté.');
    }

    public function plans(): View
    {
        return view('volailles.alimentation.plans', [
            'plans' => DB::table('modAlim_plans')->orderBy('espece')->orderBy('nom_plan')->get()
                ->map(function ($p) {
                    $p->lignes = DB::table('modAlim_plan_lignes')->where('id_plan', $p->id)->count();
                    $p->lots = DB::table('modLotVol_lots_volailles')->where('id_plan', $p->id)->where('statut', 'actif')->count();

                    return $p;
                }),
        ]);
    }

    public function creerPlan(Request $request): RedirectResponse
    {
        $d = $request->validate(['espece' => ['required', 'string', 'max:100'], 'nom_plan' => ['required', 'string', 'max:100'], 'commentaire' => ['nullable', 'string', 'max:2000']]);
        $id = DB::table('modAlim_plans')->insertGetId($d);
        Journal::enregistrer('alimentation.plan', 'ALI', 'plan', $id, apres: $d);

        return redirect()->route('alimentation.plan', $id)->with('succes', 'Plan créé : ajoutez ses rations par semaine.');
    }

    public function plan(int $id): View
    {
        return view('volailles.alimentation.plan', [
            'plan' => DB::table('modAlim_plans')->where('id', $id)->first() ?? abort(404),
            'lignes' => DB::table('modAlim_plan_lignes')->where('id_plan', $id)->orderBy('semaine')->orderBy('id')->get(),
            'aliments' => DB::table('modAlim_types')->orderBy('nom_aliment')->get()->keyBy('id'),
        ]);
    }

    public function ajouterLigne(Request $request, int $id): RedirectResponse
    {
        DB::table('modAlim_plans')->where('id', $id)->exists() || abort(404);
        $d = $request->validate([
            'semaine' => ['required', 'integer', 'min:1', 'max:520'],
            'qte_par_tete' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'is_transition' => ['nullable', 'boolean'],
            'id_aliment' => ['required_unless:is_transition,1', 'nullable', 'integer', 'exists:modAlim_types,id'],
            'id_aliment_source' => ['required_if:is_transition,1', 'nullable', 'integer', 'exists:modAlim_types,id'],
            'id_aliment_cible' => ['required_if:is_transition,1', 'nullable', 'integer', 'exists:modAlim_types,id', 'different:id_aliment_source'],
            'pct_source' => ['required_if:is_transition,1', 'nullable', 'numeric', 'min:0', 'max:100'],
            'pct_cible' => ['required_if:is_transition,1', 'nullable', 'numeric', 'min:0', 'max:100'],
        ], [], ['qte_par_tete' => 'ration', 'id_aliment' => 'aliment', 'id_aliment_source' => 'aliment source', 'id_aliment_cible' => 'aliment cible', 'pct_source' => '% source', 'pct_cible' => '% cible']);
        $transition = $request->boolean('is_transition');
        if ($transition && round((float) $d['pct_source'] + (float) $d['pct_cible']) != 100) {
            throw new RegleMetier('Les pourcentages source et cible doivent totaliser 100 %.');
        }
        $ligne = [
            'id_plan' => $id, 'semaine' => (int) $d['semaine'], 'qte_par_tete' => (float) $d['qte_par_tete'], 'is_transition' => $transition ? 1 : 0,
            'id_aliment' => $transition ? (int) $d['id_aliment_source'] : (int) $d['id_aliment'],
            'id_aliment_source' => $transition ? (int) $d['id_aliment_source'] : null, 'id_aliment_cible' => $transition ? (int) $d['id_aliment_cible'] : null,
            'pct_source' => $transition ? $d['pct_source'] : null, 'pct_cible' => $transition ? $d['pct_cible'] : null,
        ];
        $idLigne = DB::table('modAlim_plan_lignes')->insertGetId($ligne);
        Journal::enregistrer('alimentation.plan_ligne', 'ALI', 'plan', $id, apres: $ligne + ['ligne' => $idLigne]);

        return back()->with('succes', 'Ration ajoutée.');
    }

    public function supprimerLigne(int $id, int $ligne): RedirectResponse
    {
        $l = DB::table('modAlim_plan_lignes')->where('id', $ligne)->where('id_plan', $id)->first() ?? abort(404);
        DB::table('modAlim_plan_lignes')->where('id', $ligne)->delete();
        Journal::enregistrer('alimentation.plan_ligne_suppression', 'ALI', 'plan', $id, (array) $l);

        return back()->with('succes', 'Ration supprimée.');
    }

    public function consommation(Request $request): View
    {
        $f = $request->validate(['du' => ['nullable', 'date'], 'au' => ['nullable', 'date', 'after_or_equal:du']]);
        $du = $f['du'] ?? today()->subDays(6)->toDateString();
        $au = $f['au'] ?? today()->toDateString();
        $lots = DB::table('modLotVol_lots_volailles')->where('statut', 'actif')->orderBy('nom_lot')->get()
            ->map(fn ($l) => ['lot' => $l, 'c' => Alimentation::consommation($l, $du, $au)]);
        $parAliment = DB::table('modAlim_distributions as d')->join('modAlim_types as t', 't.id', '=', 'd.id_type_aliment')
            ->whereBetween('d.date_distribution', [$du, $au])->groupBy('t.nom_aliment')->orderBy('t.nom_aliment')
            ->select('t.nom_aliment', DB::raw('SUM(d.quantite_totale) g'))->get();

        return view('volailles.alimentation.consommation', ['du' => $du, 'au' => $au, 'lots' => $lots, 'parAliment' => $parAliment, 'jours' => (int) Carbon::parse($du)->diffInDays(Carbon::parse($au)) + 1]);
    }
}

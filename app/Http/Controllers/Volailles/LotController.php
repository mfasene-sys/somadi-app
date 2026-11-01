<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\Alimentation;
use App\Domaine\LotsVolailles;
use App\Domaine\Pesees;
use App\Domaine\Soins;
use App\Http\Controllers\Controller;
use App\Support\Empreinte;
use App\Support\Lieux;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LotController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['vue' => ['nullable', Rule::in(['actifs', 'archives'])], 'q' => ['nullable', 'string', 'max:100']]);
        $vue = $f['vue'] ?? 'actifs';
        $lots = DB::table('modLotVol_lots_volailles')
            ->where('statut', $vue === 'actifs' ? 'actif' : 'archive')
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where('nom_lot', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $t).'%'))
            ->orderByDesc('date_entree')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('volailles.lots.index', ['lots' => $lots, 'vue' => $vue, 'q' => $f['q'] ?? '']);
    }

    public function create(): View
    {
        return view('volailles.lots.form', ['lot' => null] + $this->listes());
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->valider($request);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return LotsVolailles::creer($d, $request->boolean('programme_soins'));
        });

        return redirect()->route('lots.show', $id)->with('succes', 'Lot créé.');
    }

    public function show(int $id): View
    {
        $lot = DB::table('modLotVol_lots_volailles')->where('id', $id)->first() ?? abort(404);
        $serie = Pesees::serie($id);

        return view('volailles.lots.show', [
            'lot' => $lot,
            'age' => LotsVolailles::age($lot),
            'mouvements' => DB::table('modLotVol_mouvements')->where('id_lot', $id)->orderByDesc('date_mouvement')->orderByDesc('id')->limit(100)->get(),
            'serie' => $serie,
            'gain' => Pesees::gain($serie),
            'soins' => DB::table('modSoinVacc_soins')->where('id_lot', $id)->orderBy('date_prevue')->get(),
            'thermo' => DB::table('modSoinVacc_Thermo_reglages')->where('id_lot', $id)->whereBetween('date_prevue', [today()->subDays(1)->toDateString(), today()->addDays(6)->toDateString()])->orderBy('date_prevue')->get(),
            'origines' => Lieux::origines(Lieux::VOLAILLE, $id, (int) $lot->effectif_en_cours),
            'aCompleter' => DB::table('modVente_Ventes_A_Completer')->where('id_lot', $id)->where('statut', 'attente')->sum('quantite'),
            'plan' => $lot->id_plan ? DB::table('modAlim_plans')->where('id', $lot->id_plan)->first() : null,
            'alimentation' => $lot->date_entree ? Alimentation::consommation($lot, $lot->date_entree, today()->toDateString()) : null,
            'incubation' => $lot->id_lot_incubation_source ? DB::table('lots_incubation')->where('id', $lot->id_lot_incubation_source)->first() : null,
            'catalogue' => DB::table('modSoinVacc_catalogue')->orderBy('nom')->get(),
        ]);
    }

    public function edit(int $id): View
    {
        $lot = DB::table('modLotVol_lots_volailles')->where('id', $id)->first() ?? abort(404);

        return view('volailles.lots.form', ['lot' => $lot, 'empreinte' => Empreinte::de($lot)] + $this->listes());
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        LotsVolailles::modifier($id, $this->valider($request), (string) $request->input('empreinte'));

        return redirect()->route('lots.show', $id)->with('succes', 'Informations du lot mises à jour (l’effectif courant n’est pas recalculé).');
    }

    public function mouvement(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate([
            'type_mouvement' => ['required', Rule::in(array_keys(LotsVolailles::TYPES))],
            'quantite' => ['required', 'integer', 'min:1', 'max:10000000'],
            'date_mouvement' => ['required', 'date', 'before_or_equal:today'],
            'commentaire' => ['required', 'string', 'max:1000'],
            'origine' => ['nullable', 'integer', 'min:0'],
        ], [], ['type_mouvement' => 'type', 'date_mouvement' => 'date', 'commentaire' => 'motif']);
        $vac = LotsVolailles::mouvement($id, $d);

        return redirect()->route('lots.show', $id)->with('succes', $vac ? 'Sortie vente enregistrée : la vente est à compléter dans le module Ventes.' : 'Mouvement enregistré.');
    }

    public function statut(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['statut' => ['required', Rule::in(['actif', 'archive'])]]);
        LotsVolailles::changerStatut($id, $d['statut']);

        return redirect()->route('lots.show', $id)->with('succes', $d['statut'] === 'archive' ? 'Lot archivé.' : 'Lot réactivé.');
    }

    public function pesee(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['date_suivi' => ['required', 'date'], 'poids_moyen' => ['required', 'string', 'max:20']], [], ['date_suivi' => 'date', 'poids_moyen' => 'poids moyen']);
        Pesees::ajouter($id, $d['date_suivi'], $d['poids_moyen']);

        return redirect()->to(route('lots.show', $id).'#pesees')->with('succes', 'Pesée enregistrée.');
    }

    public function programme(int $id): RedirectResponse
    {
        $n = Soins::programmePourLot($id);

        return redirect()->to(route('lots.show', $id).'#soins')->with('succes', $n ? "$n soins planifiés à partir du programme." : 'Tous les soins du programme étaient déjà planifiés.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom_lot' => ['required', 'string', 'max:100'],
            'espece' => ['required', 'string', 'max:50'],
            'race' => ['required', 'string', 'max:50'],
            'nombre_initial' => ['required', 'integer', 'min:1', 'max:10000000'],
            'date_entree' => ['required', 'date'],
            'origine' => ['nullable', 'string', 'max:100'],
            'id_plan' => ['nullable', 'integer', 'exists:modAlim_plans,id'],
            'remarques' => ['nullable', 'string', 'max:5000'],
        ], [], ['nom_lot' => 'nom du lot', 'nombre_initial' => 'nombre initial', 'date_entree' => 'date d’entrée', 'id_plan' => 'plan d’alimentation']);
    }

    private function listes(): array
    {
        $ref = DB::table('modRef_volailles')->orderBy('espece')->orderBy('race')->get();

        return [
            'especes' => $ref->pluck('espece')->merge(['poulet', 'caille'])->map(fn ($e) => mb_strtolower($e))->unique()->sort()->values(),
            'races' => $ref,
            'plans' => DB::table('modAlim_plans')->orderBy('nom_plan')->get(),
        ];
    }
}

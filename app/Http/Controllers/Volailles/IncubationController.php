<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\Incubation;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncubationController extends Controller
{
    public function index(Request $request): View
    {
        $vue = $request->query('vue') === 'terminees' ? 'terminees' : 'en_cours';
        $q = DB::table('lots_incubation as i')->leftJoin('bandes_parentales as b', 'b.id', '=', 'i.id_bande');
        if ($vue === 'en_cours') {
            $q->whereNull('i.date_eclosion')->whereRaw('COALESCE(i.nombre_eclos,0) = 0')->orderBy('i.date_eclosion_previsionnelle')->orderBy('i.id');
        } else {
            $q->where(fn ($w) => $w->whereNotNull('i.date_eclosion')->orWhere('i.nombre_eclos', '>', 0))->orderByDesc('i.date_eclosion')->orderByDesc('i.id');
        }

        return view('volailles.incubation.index', [
            'vue' => $vue,
            'lots' => $q->paginate(30, ['i.*', 'b.nom_bande'])->withQueryString(),
            'equipements' => Incubation::equipements(),
        ]);
    }

    public function create(): View
    {
        $lots = DB::table('lots_oeufs_ramasses as l')
            ->join('bandes_parentales as b', 'b.id', '=', 'l.id_bande')
            ->leftJoin('stock_oeufs_ramasses as s', 's.id', '=', 'l.id_stock_oeufs')
            ->where('l.type_lot', 'incuber')->where('l.statut', 'nouveau')->where('l.quantite', '>', 0)
            ->orderBy('b.nom_bande')->orderBy('s.date_ramassage')->get(['l.*', 'b.nom_bande', 'b.espece', 's.date_ramassage']);

        $parBande = $lots->groupBy('id_bande')->map(fn ($g) => [
            'nom' => $g->first()->nom_bande, 'espece' => $g->first()->espece, 'lots' => $g,
            'total' => (int) $g->sum('quantite'), 'proposee' => Incubation::dateProposee($g->pluck('date_ramassage')->all()),
        ]);

        return view('volailles.incubation.chargement', ['parBande' => $parBande, 'incubateurs' => Incubation::equipements('incubateur')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'lots' => ['required', 'array', 'min:1', 'max:500'],
            'lots.*' => ['integer', 'distinct'],
            'id_incubateur' => ['required', 'integer'],
            'date_entree' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ], ['lots.required' => 'Sélectionnez au moins un lot d’œufs.'], ['id_incubateur' => 'incubateur', 'date_entree' => 'date de chargement']);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Incubation::charger($d['lots'], (int) $d['id_incubateur'], $d['date_entree'], $d['observations'] ?? null);
        });

        return redirect()->route('incubation.show', $id)->with('succes', 'Incubateur chargé.');
    }

    public function show(int $id): View
    {
        $l = DB::table('lots_incubation as i')->leftJoin('bandes_parentales as b', 'b.id', '=', 'i.id_bande')
            ->where('i.id', $id)->first(['i.*', 'b.nom_bande']) ?? abort(404);
        $brut = DB::table('lots_incubation')->where('id', $id)->first();
        $entree = $l->date_entree ? Carbon::parse($l->date_entree) : null;

        return view('volailles.incubation.show', [
            'l' => $l,
            'empreinte' => \App\Support\Empreinte::de($brut),
            'jour' => $entree ? (int) $entree->diffInDays(today(), false) : null,
            'dateMirage' => $entree?->copy()->addDays(Incubation::JOUR_MIRAGE),
            'eclosoirs' => Incubation::equipements('eclosoir'),
            'reservations' => DB::table('resa_reservations')->where('id_lot_prevu', $id)->count(),
            'lotVolaille' => DB::table('modLotVol_lots_volailles')->where('id_lot_incubation_source', $id)->first(),
            'prevision' => DB::table('resa_lots_previsionnels')->where('id_lot', $id)->first(),
        ]);
    }

    public function ajuster(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['nombre_oeufs' => ['required', 'integer', 'min:1'], 'empreinte' => ['required', 'string']], [], ['nombre_oeufs' => 'nombre d’œufs']);
        Incubation::ajuster($id, (int) $d['nombre_oeufs'], $d['empreinte']);

        return back()->with('succes', 'Nombre d’œufs ajusté.');
    }

    public function mirer(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['nombre_fertiles' => ['required', 'integer', 'min:0'], 'date_mirage' => ['required', 'date'], 'empreinte' => ['required', 'string']], [], ['nombre_fertiles' => 'œufs fertiles', 'date_mirage' => 'date du mirage']);
        Incubation::mirer($id, (int) $d['nombre_fertiles'], $d['date_mirage'], $d['empreinte']);

        return back()->with('succes', 'Mirage enregistré.');
    }

    public function transferer(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate([
            'eclosoir1' => ['required', 'integer'], 'eclosoir2' => ['nullable', 'integer'],
            'date_transfert' => ['required', 'date'], 'empreinte' => ['required', 'string'],
        ], [], ['eclosoir1' => 'éclosoir principal', 'eclosoir2' => 'second éclosoir', 'date_transfert' => 'date du transfert']);
        $idB = Incubation::transferer($id, (int) $d['eclosoir1'], isset($d['eclosoir2']) ? (int) $d['eclosoir2'] : null, $d['date_transfert'], $d['empreinte']);

        return back()->with('succes', $idB ? "Transfert enregistré avec scission : la partie B est le lot n° $idB." : 'Transfert enregistré.');
    }

    public function eclore(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate([
            'date_eclosion' => ['required', 'date'], 'nombre_eclos' => ['required', 'integer', 'min:0'],
            'methode' => ['required', Rule::in(['mesure', 'estimation'])], 'race' => ['nullable', 'string', 'max:50'],
            'programme_soins' => ['nullable', 'boolean'], 'empreinte' => ['required', 'string'],
        ], [], ['nombre_eclos' => 'nombre éclos', 'date_eclosion' => 'date d’éclosion']);
        $lot = Incubation::eclore($id, $d, $d['empreinte']);

        return $lot
            ? redirect()->route('lots.show', $lot)->with('succes', 'Éclosion enregistrée : le lot de poussins est créé.')
            : back()->with('succes', 'Éclosion enregistrée sans poussin : le lot d’incubation est clôturé.');
    }
}

<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\Reproducteurs;
use App\Http\Controllers\Controller;
use App\Support\Lieux;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReproducteurController extends Controller
{
    public function index(Request $request): View
    {
        $vue = $request->query('vue') === 'reformees' ? 'reformees' : 'actives';
        $bandes = DB::table('bandes_parentales')
            ->when($vue === 'actives', fn ($q) => $q->where(fn ($w) => $w->where('reformee', 0)->orWhereNull('reformee')), fn ($q) => $q->where('reformee', 1))
            ->orderBy('nom_bande')->get();

        return view('volailles.reproducteurs.index', compact('bandes', 'vue'));
    }

    public function create(): View
    {
        return view('volailles.reproducteurs.form', ['bande' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->valider($request, true);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Reproducteurs::creer($d);
        });

        return redirect()->route('reproducteurs.show', $id)->with('succes', 'Bande créée.');
    }

    public function show(int $id): View
    {
        $bande = DB::table('bandes_parentales')->where('id', $id)->first() ?? abort(404);
        $mouvements = DB::table('modRepro_mouvements')->where('id_bande', $id)->orderByDesc('date_mouvement')->orderByDesc('id')->paginate(25);

        return view('volailles.reproducteurs.show', [
            'bande' => $bande,
            'mouvements' => $mouvements,
            'origines' => ['mâle' => Lieux::origines(Lieux::REPRO_M, $id, (int) $bande->nb_males), 'femelle' => Lieux::origines(Lieux::REPRO_F, $id, (int) $bande->nb_femelles)],
            'ponte' => DB::table('suivi_ponte')->where('id_bande', $id)->orderByDesc('date')->limit(7)->get(),
        ]);
    }

    public function edit(int $id): View
    {
        return view('volailles.reproducteurs.form', ['bande' => DB::table('bandes_parentales')->where('id', $id)->first() ?? abort(404)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Reproducteurs::modifier($id, $this->valider($request, false), (string) $request->input('empreinte'));

        return redirect()->route('reproducteurs.show', $id)->with('succes', 'Bande mise à jour.');
    }

    public function mouvement(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate([
            'type_mouvement' => ['required', Rule::in(array_keys(Reproducteurs::TYPES))],
            'sexe' => ['required', Rule::in(array_keys(Reproducteurs::SEXES))],
            'quantite' => ['required', 'integer', 'min:1', 'max:1000000'],
            'date_mouvement' => ['required', 'date', 'before_or_equal:today'],
            'commentaire' => ['required', 'string', 'max:1000'],
            'origine_male' => ['nullable', 'integer', 'min:0'],
            'origine_femelle' => ['nullable', 'integer', 'min:0'],
        ], [], ['type_mouvement' => 'type', 'date_mouvement' => 'date', 'commentaire' => 'motif']);
        $d['origine'] = $d['sexe'] === 'mâle' ? ($d['origine_male'] ?? null) : ($d['origine_femelle'] ?? null);
        Reproducteurs::mouvement($id, $d);

        return redirect()->route('reproducteurs.show', $id)->with('succes', 'Mouvement enregistré.');
    }

    private function valider(Request $request, bool $creation): array
    {
        return $request->validate([
            'nom_bande' => ['required', 'string', 'max:100'],
            'espece' => [$creation ? 'required' : 'prohibited', Rule::in(['poulet', 'caille'])],
            'nb_males' => [$creation ? 'required' : 'prohibited', 'integer', 'min:0', 'max:1000000'],
            'nb_femelles' => [$creation ? 'required' : 'prohibited', 'integer', 'min:0', 'max:1000000'],
            'date_mise_en_place' => ['nullable', 'date', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:5000'],
            'reformee' => ['nullable', 'boolean'],
        ], [], ['nom_bande' => 'nom', 'nb_males' => 'mâles', 'nb_femelles' => 'femelles', 'date_mise_en_place' => 'date de mise en place']);
    }
}

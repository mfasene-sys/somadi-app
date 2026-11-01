<?php

namespace App\Http\Controllers\Ovins;

use App\Domaine\Ovins\Animaux;
use App\Domaine\Ovins\Medias;
use App\Domaine\Ovins\Referentiels;
use App\Domaine\Ovins\Reproduction;
use App\Domaine\Ovins\Soins;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnimalController extends Controller
{
    private const FILTRES = ['sites', 'proprietaires', 'sexes', 'statuts', 'ages', 'repros'];

    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'lot' => ['nullable', 'integer'],
            'sites' => ['nullable', 'array'], 'sites.*' => ['integer'], 'proprietaires' => ['nullable', 'array'], 'proprietaires.*' => ['integer'],
            'sexes' => ['nullable', 'array'], 'sexes.*' => [Rule::in(['M', 'F'])],
            'statuts' => ['nullable', 'array'], 'statuts.*' => [Rule::in(array_merge(['tous'], array_keys(config('somadi.ovins.statuts'))))],
            'ages' => ['nullable', 'array'], 'ages.*' => [Rule::in(array_keys(Animaux::AGES))],
            'repros' => ['nullable', 'array'], 'repros.*' => [Rule::in(array_keys(Animaux::REPROS))],
            'reset' => ['nullable'],
        ]);
        // RG-OVA-003 : filtres mémorisés pour la session.
        if ($request->has('reset')) {
            session()->forget('ovins.filtres');
            $f = [];
        } elseif ($request->query()) {
            session(['ovins.filtres' => $f]);
        } else {
            $f = session('ovins.filtres', []);
        }
        $q = Animaux::requete($f);
        $total = (clone $q)->count();
        $sexes = (clone $q)->reorder()->groupBy('a.sexe')->select('a.sexe', DB::raw('COUNT(*) n'))->pluck('n', 'sexe');

        return view('ovins.animaux.index', [
            'animaux' => $q->orderBy('a.code_animal')->paginate(50)->withQueryString(),
            'total' => $total, 'parSexe' => $sexes, 'f' => $f,
            'sites' => Referentiels::sites(), 'lots' => Referentiels::lots(), 'proprietaires' => Referentiels::proprietaires(),
        ]);
    }

    private function listes(?int $sauf = null): array
    {
        $parents = DB::table('OVINS_animaux')->when($sauf, fn ($q) => $q->where('id_animal', '<>', $sauf))->orderBy('code_animal')
            ->get(['id_animal', 'code_animal', 'nom_animal', 'sexe', 'statut']);

        return [
            'sites' => Referentiels::sites(true), 'lots' => Referentiels::lots(true), 'proprietaires' => Referentiels::proprietaires(true),
            'meres' => $parents->where('sexe', 'F'), 'peres' => $parents->where('sexe', 'M'),
        ];
    }

    public function create(): View
    {
        return view('ovins.animaux.form', ['a' => null] + $this->listes());
    }

    private function valider(Request $request, bool $creation): array
    {
        return $request->validate([
            'code_animal' => ['nullable', 'string', 'max:100'], 'nom_animal' => ['nullable', 'string', 'max:50'], 'sexe' => ['required', Rule::in(['M', 'F'])],
            'race' => ['nullable', 'string', 'max:100'], 'date_naissance' => ['nullable', 'date'], 'date_entree' => ['nullable', 'date'],
            'origine' => ['nullable', 'string', 'max:100'], 'id_proprietaire' => ['nullable', 'integer'], 'id_mere' => ['nullable', 'integer'], 'id_pere' => ['nullable', 'integer'],
            'poids_naissance' => ['nullable', 'string', 'max:10'], 'poids_dernier' => ['nullable', 'string', 'max:10'], 'prix_achat' => ['nullable', 'string', 'max:20'],
            'observations' => ['nullable', 'string', 'max:5000'], 'est_reproducteur' => ['nullable', 'boolean'],
        ] + ($creation ? ['id_site' => ['required', 'integer'], 'id_lot' => ['nullable', 'integer'], 'compter' => ['nullable', 'boolean']] : []),
            [], ['code_animal' => 'numéro de boucle', 'id_site' => 'site']);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->valider($request, true);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Animaux::creer($d, $request->boolean('compter'));
        });

        return redirect()->route('ovins.animaux.show', $id)->with('succes', 'Fiche créée.');
    }

    public function show(int $id): View
    {
        $a = Animaux::fiche($id);

        return view('ovins.animaux.show', [
            'a' => $a,
            'medias' => Medias::liste($id),
            'chronologie' => Animaux::chronologie($id),
            'repro' => $a->sexe === 'F' ? Reproduction::historique($id) : collect(),
            'soins' => Soins::requete(['animal' => $id])->orderByDesc('r.date_soin')->limit(50)->get(),
            'enfants' => DB::table('OVINS_animaux')->where('id_mere', $id)->orWhere('id_pere', $id)->orderBy('date_naissance')->get(),
            'sortie' => DB::table('OVINS_sorties as s')->leftJoin('modVente_ovins_liaisons as l', 'l.id_sortie', '=', 's.id_sortie')
                ->where('s.id_animal', $id)->orderByDesc('s.id_sortie')->first(['s.*', 'l.id_vente']),
            'lots' => Referentiels::lots(true),
            'modeles' => DB::table('OVINS_modeles_soins')->where('actif', 1)->whereIn('cible', ['tous', 'animal'])->orderBy('nom_modele')->get(),
        ]);
    }

    public function edit(int $id): View
    {
        return view('ovins.animaux.form', ['a' => DB::table('OVINS_animaux')->where('id_animal', $id)->first() ?? abort(404)] + $this->listes($id));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Animaux::modifier($id, $this->valider($request, false), (string) $request->input('empreinte'));

        return redirect()->route('ovins.animaux.show', $id)->with('succes', 'Fiche mise à jour.');
    }

    public function arbre(int $id): View
    {
        return view('ovins.animaux.arbre', ['a' => Animaux::fiche($id)] + Animaux::arbre($id));
    }

    public function ajouterMedias(Request $request, int $id): RedirectResponse
    {
        $request->validate(['fichiers' => ['required', 'array', 'min:1', 'max:10'], 'fichiers.*' => ['file'], 'stade_age' => ['required', Rule::in(array_keys(Medias::STADES))],
            'description' => ['nullable', 'string', 'max:255']], ['fichiers.required' => 'Choisissez au moins un fichier.']);
        $ok = 0;
        $erreurs = [];
        foreach ($request->file('fichiers') as $f) {
            try {
                Medias::ajouter($id, $f, $request->input('stade_age'), $request->input('description'));
                $ok++;
            } catch (\App\Exceptions\RegleMetier $e) {
                $erreurs[] = $e->getMessage();
            }
        }
        $r = redirect()->to(route('ovins.animaux.show', $id).'#medias');

        return $erreurs ? $r->with('erreur', implode(' ', $erreurs).($ok ? " ($ok fichier(s) ajouté(s).)" : '')) : $r->with('succes', "$ok fichier(s) ajouté(s).");
    }

    public function supprimerMedia(int $id, int $media): RedirectResponse
    {
        Medias::supprimer($id, $media);

        return redirect()->to(route('ovins.animaux.show', $id).'#medias')->with('succes', 'Média supprimé.');
    }

    public function media(int $media): BinaryFileResponse
    {
        $m = DB::table('OVINS_animaux_medias')->where('id_media', $media)->first() ?? abort(404);
        $p = Medias::chemin($m) ?? abort(404);

        return response()->file($p, ['Content-Type' => $m->mime_type ?: 'application/octet-stream', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}

<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\Ponte;
use App\Domaine\Reproducteurs;
use App\Http\Controllers\Controller;
use App\Support\Empreinte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PonteController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'bande' => ['nullable', 'integer'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);
        $date = $f['date'] ?? today()->toDateString();
        $bandes = Reproducteurs::actives();
        $bandeChoisie = isset($f['bande']) ? $bandes->firstWhere('id', (int) $f['bande']) : null;
        $existante = $bandeChoisie ? DB::table('suivi_ponte')->where('id_bande', $bandeChoisie->id)->where('date', $date)->orderBy('id')->get() : collect();

        $aValider = DB::table('suivi_ponte as p')->leftJoin('bandes_parentales as b', 'b.id', '=', 'p.id_bande')
            ->whereRaw('LOWER(TRIM(p.statut)) = ?', ['attente'])->whereNull('p.total_oeufs_valides')->whereNull('p.nb_oeufs_defectueux')
            ->orderBy('p.date')->orderBy('b.nom_bande')->get(['p.*', 'b.nom_bande']);

        $du = $f['du'] ?? today()->subDays(13)->toDateString();
        $au = $f['au'] ?? today()->toDateString();
        $historique = DB::table('suivi_ponte as p')->leftJoin('bandes_parentales as b', 'b.id', '=', 'p.id_bande')
            ->whereBetween('p.date', [$du, $au])
            ->when(isset($f['bande']), fn ($q) => $q->where('p.id_bande', $f['bande']))
            ->orderByDesc('p.date')->orderBy('b.nom_bande')->paginate(30, ['p.*', 'b.nom_bande'])->withQueryString();

        return view('volailles.ponte.index', [
            'bandes' => $bandes, 'bande' => $bandeChoisie, 'date' => $date,
            'existante' => $existante->count() === 1 ? $existante->first() : null, 'doublon' => $existante->count() > 1,
            'aValider' => $aValider, 'historique' => $historique, 'du' => $du, 'au' => $au,
        ]);
    }

    /** Courbe du taux de ponte et de la qualité (part de défectueux), par bande et par jour. */
    public function courbe(Request $request): View
    {
        $f = $request->validate([
            'bande' => ['nullable', 'string', 'max:10'],
            'jours' => ['nullable', 'integer', 'in:14,30,60,90,180,365'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ]);
        $jours = (int) ($f['jours'] ?? 30);
        $au = $f['au'] ?? today()->toDateString();
        $du = $f['du'] ?? today()->subDays($jours - 1)->toDateString();
        $toutes = DB::table('bandes_parentales')->orderBy('reformee')->orderBy('nom_bande')->get();
        $choix = $f['bande'] ?? 'toutes';
        $ids = $choix === 'toutes'
            ? $toutes->where('reformee', '!=', 1)->pluck('id')->all()
            : [(int) $choix];

        $lignes = DB::table('suivi_ponte')->whereIn('id_bande', $ids ?: [0])->whereBetween('date', [$du, $au])
            ->groupBy('id_bande', 'date')->orderBy('date')
            ->select('id_bande', 'date',
                DB::raw('AVG(taux_ponte) taux'),
                DB::raw('SUM(COALESCE(ramassage_matin,0) + COALESCE(ramassage_apres_midi,0)) total'),
                DB::raw("SUM(CASE WHEN LOWER(TRIM(statut)) = 'valide' THEN nb_oeufs_defectueux END) defect"),
                DB::raw("SUM(CASE WHEN LOWER(TRIM(statut)) = 'valide' THEN COALESCE(total_oeufs_valides,0) + COALESCE(nb_oeufs_defectueux,0) END) controles"))
            ->get();

        $dates = [];
        for ($d = \Illuminate\Support\Carbon::parse($du); $d->lte(\Illuminate\Support\Carbon::parse($au)); $d->addDay()) {
            $dates[] = $d->toDateString();
        }
        $couleurs = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
        $series = [];
        $qualite = [];
        // La couleur suit la bande (son rang dans la liste complète), jamais l'ordre d'affichage.
        $rang = $toutes->pluck('id')->flip();
        foreach ($lignes->groupBy('id_bande') as $idBande => $g) {
            $b = $toutes->firstWhere('id', $idBande);
            $couleur = $couleurs[($rang[$idBande] ?? 0) % 8];
            $series[] = ['nom' => $b->nom_bande ?? '#'.$idBande, 'couleur' => $couleur,
                'points' => $g->mapWithKeys(fn ($l) => [$l->date => $l->taux !== null ? round((float) $l->taux, 2) : null])->filter(fn ($v) => $v !== null)->all()];
            $qualite[] = ['nom' => $b->nom_bande ?? '#'.$idBande, 'couleur' => $couleur,
                'points' => $g->filter(fn ($l) => (int) $l->controles > 0)->mapWithKeys(fn ($l) => [$l->date => round(100 * (int) $l->defect / (int) $l->controles, 2)])->all()];
        }
        $total = (int) $lignes->sum('total');
        $controles = (int) $lignes->sum('controles');

        return view('volailles.ponte.courbe', [
            'bandes' => $toutes, 'choix' => $choix, 'jours' => $jours, 'du' => $du, 'au' => $au, 'dates' => $dates,
            'series' => array_slice($series, 0, 8), 'qualite' => array_slice($qualite, 0, 8), 'tronque' => count($series) > 8,
            'lignes' => $lignes, 'noms' => $toutes->pluck('nom_bande', 'id'),
            'synthese' => [
                'taux_moyen' => $lignes->whereNotNull('taux')->avg('taux'),
                'oeufs' => $total,
                'defect_pct' => $controles > 0 ? 100 * (int) $lignes->sum('defect') / $controles : null,
                'jours_saisis' => $lignes->pluck('date')->unique()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'id_bande' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'ramassage_matin' => ['required', 'integer', 'min:0', 'max:1000000'],
            'ramassage_apres_midi' => ['required', 'integer', 'min:0', 'max:1000000'],
            'temoin' => ['nullable', 'boolean'],
            'empreinte' => ['nullable', 'string', 'size:64'],
        ], [], ['ramassage_matin' => 'matin', 'ramassage_apres_midi' => 'après-midi']);
        Ponte::saisir($d);

        return redirect()->route('ponte.index', ['bande' => $d['id_bande'], 'date' => $d['date']])
            ->with('succes', 'Ramassage enregistré : il reste à le valider.');
    }

    public function valider(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['nb_oeufs_defectueux' => ['required', 'integer', 'min:0'], 'empreinte' => ['required', 'string']], [], ['nb_oeufs_defectueux' => 'œufs défectueux']);
        Ponte::valider($id, (int) $d['nb_oeufs_defectueux'], $d['empreinte']);

        return back()->with('succes', 'Ramassage validé : les œufs valides sont en stock.');
    }

    public static function empreinte(object $l): string
    {
        return Empreinte::de($l);
    }
}

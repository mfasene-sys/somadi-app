<?php

namespace App\Http\Controllers\Ovins;

use App\Domaine\Ovins\Inventaires;
use App\Domaine\Ovins\Referentiels;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventaireController extends Controller
{
    public function index(): View
    {
        return view('ovins.inventaires.index', [
            'inventaires' => DB::table('OVINS_inventaires as i')
                ->leftJoinSub(DB::table('OVINS_inventaire_lignes')->groupBy('inventaire_id')->select('inventaire_id', DB::raw("SUM(nature='attendu' AND pointage<>'non_pointe') pointes"), DB::raw("SUM(nature='supplementaire') supp")), 'x', 'x.inventaire_id', '=', 'i.id')
                ->orderByDesc('i.id')->get(['i.*', 'x.pointes', 'x.supp']),
        ]);
    }

    public function create(Request $request): View
    {
        $f = $request->only(['site', 'lot', 'proprietaire']);

        return view('ovins.inventaires.form', ['sites' => Referentiels::sites(true), 'lots' => Referentiels::lots(true), 'proprietaires' => Referentiels::proprietaires(),
            'f' => $f, 'compte' => Inventaires::compterPerimetre($f)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate(['titre' => ['required', 'string', 'max:150'], 'site' => ['nullable', 'integer'], 'lot' => ['nullable', 'integer'], 'proprietaire' => ['nullable', 'integer'], '_cle' => ['required', 'uuid']]);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Inventaires::ouvrir($d, $d['_cle']);
        });

        return redirect()->route('ovins.inventaires.show', $id)->with('succes', 'Inventaire ouvert : la liste attendue est figée.');
    }

    public function show(Request $request, int $id): View
    {
        $inv = DB::table('OVINS_inventaires')->where('id', $id)->first() ?? abort(404);
        $f = $request->validate(['etat' => ['nullable', Rule::in(array_keys(Inventaires::POINTAGES))], 'q' => ['nullable', 'string', 'max:100']]);
        $lignes = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $id)
            ->when($f['etat'] ?? null, fn ($q, $e) => $q->where('pointage', $e))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('code_animal', 'like', "%$t%")->orWhere('nom_animal', 'like', "%$t%")))
            ->orderBy('numero')->paginate(100)->withQueryString();

        return view('ovins.inventaires.show', [
            'inv' => $inv, 'lignes' => $lignes, 'f' => $f, 'synthese' => Inventaires::synthese($id), 'rapprochement' => Inventaires::rapprochement($inv),
            'journal' => DB::table('OVINS_inventaire_journal')->where('inventaire_id', $id)->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    private function controle(Request $request): array
    {
        return $request->validate(['controleur' => ['nullable', 'string', 'max:100'], 'date_controle' => ['nullable', 'date'], 'site_constate' => ['nullable', 'string', 'max:150'],
            'lot_constate' => ['nullable', 'string', 'max:150'], 'commentaire' => ['nullable', 'string', 'max:2000']]);
    }

    public function pointer(Request $request, int $id, int $ligne): RedirectResponse
    {
        $d = $request->validate(['pointage' => ['required', Rule::in(array_keys(Inventaires::POINTAGES))], 'version' => ['required', 'integer']]) + $this->controle($request);
        Inventaires::pointer($id, $ligne, $d['pointage'], (int) $d['version'], $d);
        session(['ovins.controleur' => $d['controleur'] ?? null]);

        return redirect()->to(url()->previous().'#ligne-'.$ligne)->with('succes', 'Pointage enregistré.');
    }

    public function groupe(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['lignes' => ['required', 'array', 'min:1', 'max:50'], 'lignes.*' => ['integer']], ['lignes.required' => 'Cochez les animaux vus.']) + $this->controle($request);
        $n = Inventaires::pointerGroupe($id, $d['lignes'], $d);
        session(['ovins.controleur' => $d['controleur'] ?? null]);

        return back()->with('succes', "$n animal(aux) pointé(s) présents.");
    }

    public function ajouter(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['code_animal' => ['required', 'string', 'max:100'], '_cle' => ['required', 'uuid']]) + $this->controle($request);
        $r = Inventaires::ajouter($id, $d, $d['_cle']);

        return $r['existante']
            ? redirect()->to(route('ovins.inventaires.show', $id).'?q='.urlencode($d['code_animal']).'#ligne-'.$r['id'])->with('erreur', "Ce numéro figure déjà à la ligne n° {$r['numero']} : pointez cette ligne.")
            : back()->with('succes', "Observation ajoutée (ligne n° {$r['numero']}).");
    }

    public function cloturer(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['version' => ['required', 'integer'], 'bilan' => ['required', 'string', 'max:5000'], 'confirmation' => ['accepted']], ['confirmation.accepted' => 'Cochez la case de confirmation.']);
        Inventaires::cloturer($id, (int) $d['version'], $d['bilan']);

        return back()->with('succes', 'Inventaire clôturé. Les absences éventuelles restent à traiter par une sortie, si elles sont confirmées.');
    }

    public function feuille(int $id): Response
    {
        $inv = DB::table('OVINS_inventaires')->where('id', $id)->first() ?? abort(404);
        $lignes = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $id)->where('nature', 'attendu')->orderBy('numero')->get();
        $o = new Options;
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('isRemoteEnabled', false);
        $pdf = new Dompdf($o);
        $pdf->loadHtml(view('ovins.inventaires.feuille', compact('inv', 'lignes'))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="inventaire-ovins-'.$id.'.pdf"']);
    }
}

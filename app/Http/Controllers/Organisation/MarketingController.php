<?php

namespace App\Http\Controllers\Organisation;

use App\Domaine\Organisation\Marketing;
use App\Domaine\Ventes;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MarketingController extends Controller
{
    public function index(): View
    {
        $stats = DB::table('modMkt_logs')->groupBy('id_campagne', 'action')->select('id_campagne', 'action', DB::raw('COUNT(DISTINCT client_tel_norm) n'))->get()->groupBy('id_campagne');

        return view('organisation.marketing.index', [
            'campagnes' => DB::table('modMkt_campagnes as c')->join('modMkt_templates as t', 't.id', '=', 'c.id_template')->orderByDesc('c.id')->get(['c.*', 't.titre as modele']),
            'stats' => $stats, 'modeles' => DB::table('modMkt_templates')->where('is_actif', 1)->orderBy('titre')->get(),
        ]);
    }

    public function creerCampagne(Request $request): RedirectResponse
    {
        $d = $request->validate(['titre' => ['required', 'string', 'max:150'], 'id_template' => ['required', 'integer']], [], ['id_template' => 'modèle']);
        $id = Marketing::creerCampagne($d['titre'], (int) $d['id_template']);

        return redirect()->route('marketing.campagne', $id)->with('succes', 'Campagne créée : choisissez les clients.');
    }

    public function campagne(Request $request, int $id): View
    {
        $c = DB::table('modMkt_campagnes as c')->join('modMkt_templates as t', 't.id', '=', 'c.id_template')->where('c.id', $id)->first(['c.*', 't.titre as modele', 't.message', 't.image_path']) ?? abort(404);
        $f = $request->validate(['du' => ['nullable', 'date'], 'au' => ['nullable', 'date'], 'produit' => ['nullable', 'string', 'max:50'], 'montant_min' => ['nullable', 'string', 'max:20'],
            'exclure' => ['nullable', Rule::in(['0', '1'])], 'etat' => ['nullable', Rule::in(['', 'a_faire', 'OPEN_WA', 'MARQUE_ENVOYE', 'IGNORE'])], 'mode' => ['nullable', Rule::in(['liste', 'suivant'])]]);
        $f += ['du' => today()->subYear()->toDateString(), 'exclure' => '1'];
        $clients = Marketing::clients($f, $id);
        $mode = $f['mode'] ?? 'liste';
        $page = max(1, (int) $request->query('page', 1));
        $liste = $mode === 'suivant'
            ? $clients->filter(fn ($x) => in_array($x->etat, [null, 'OPEN_WA'], true))->take(1)->values()
            : new LengthAwarePaginator($clients->forPage($page, 50)->values(), $clients->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('organisation.marketing.campagne', ['c' => $c, 'f' => $f, 'liste' => $liste, 'mode' => $mode, 'total' => $clients->count(),
            'produits' => collect(Ventes::produits())->map(fn ($p) => $p['libelle'])->all()]);
    }

    public function statut(Request $request, int $id): RedirectResponse
    {
        Marketing::changerStatut($id, (string) $request->validate(['statut' => ['required', 'string']])['statut']);

        return back()->with('succes', 'Statut de la campagne mis à jour.');
    }

    /** OPEN_WA : on journalise puis on ouvre WhatsApp ; l'envoi reste à faire par l'utilisateur. */
    public function action(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(array_keys(Marketing::ACTIONS))], 'tel' => ['required', 'string', 'max:50'], 'nom' => ['nullable', 'string', 'max:150'], 'texte' => ['nullable', 'string', 'max:4000']]);
        $n = Marketing::journaliser($id, $d['action'], $d['tel'], $d['nom'] ?? null);
        if ($d['action'] === 'OPEN_WA') {
            return redirect()->away(Marketing::lienWhatsapp($n, (string) ($d['texte'] ?? '')));
        }

        return back()->with('succes', $d['action'] === 'MARQUE_ENVOYE' ? 'Marqué envoyé (déclaration, sans accusé de WhatsApp).' : 'Contact ignoré pour cette campagne.');
    }

    public function modeles(): View
    {
        return view('organisation.marketing.modeles', ['modeles' => DB::table('modMkt_templates')->orderByDesc('is_actif')->orderBy('titre')->get()]);
    }

    public function enregistrerModele(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate(['titre' => ['required', 'string', 'max:120'], 'message' => ['required', 'string', 'max:4000'], 'is_actif' => ['nullable', 'boolean'], 'image' => ['nullable', 'file']]);
        Marketing::enregistrerModele($id, $d, $request->file('image'));

        return back()->with('succes', 'Modèle enregistré.');
    }

    public function image(int $id): BinaryFileResponse
    {
        $t = DB::table('modMkt_templates')->where('id', $id)->first() ?? abort(404);

        return response()->file(Marketing::cheminImage($t) ?? abort(404));
    }

    public function exclusions(): View
    {
        return view('organisation.marketing.exclusions', ['liste' => DB::table('modMkt_blacklist')->orderByDesc('id')->get()]);
    }

    public function exclure(Request $request): RedirectResponse
    {
        $d = $request->validate(['tel' => ['required', 'string', 'max:30'], 'motif' => ['nullable', 'string', 'max:150']]);
        Marketing::blacklister($d['tel'], $d['motif'] ?? null);

        return back()->with('succes', 'Numéro ajouté à la liste d’exclusion.');
    }

    public function retirer(int $id): RedirectResponse
    {
        Marketing::retirerBlacklist($id);

        return back()->with('succes', 'Numéro retiré de la liste d’exclusion.');
    }

    public function journal(Request $request): View
    {
        return view('organisation.marketing.journal', ['logs' => DB::table('modMkt_logs as l')->join('modMkt_campagnes as c', 'c.id', '=', 'l.id_campagne')
            ->when($request->integer('campagne'), fn ($q, $c) => $q->where('l.id_campagne', $c))->orderByDesc('l.id')->paginate(50, ['l.*', 'c.titre'])->withQueryString()]);
    }
}

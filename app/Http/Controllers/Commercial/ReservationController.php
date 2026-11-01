<?php

namespace App\Http\Controllers\Commercial;

use App\Domaine\Incubation;
use App\Domaine\Reservations;
use App\Domaine\Tresorerie;
use App\Domaine\Ventes;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $statut = $request->query('statut', 'actives');
        $previsions = DB::table('resa_lots_previsionnels as p')->join('lots_incubation as i', 'i.id', '=', 'p.id_lot')
            ->whereNull('i.date_eclosion')->whereRaw('COALESCE(i.nombre_eclos,0) = 0')
            ->orderBy('p.date_eclosion_prevue')->get(['p.*', 'i.nom_lot', 'i.nombre_oeufs', 'i.nombre_fertiles', 'i.date_mirage']);
        $aTraiter = DB::table('resa_reservations as r')->join('lots_incubation as i', 'i.id', '=', 'r.id_lot_prevu')
            ->where(fn ($q) => $q->whereNotNull('i.date_eclosion')->orWhere('i.nombre_eclos', '>', 0))
            ->where(fn ($q) => $q->where('r.statut', 'pré-réservé')->orWhere('r.statut', 'like', 'partiel%')->orWhere('r.statut', Reservations::PARTIEL))
            ->groupBy('i.id', 'i.nom_lot', 'i.nombre_eclos', 'i.date_eclosion')
            ->select('i.id', 'i.nom_lot', 'i.nombre_eclos', 'i.date_eclosion', DB::raw('COUNT(*) n'), DB::raw('SUM(r.quantite - r.quantite_servie) manque'))->get();
        $reservations = DB::table('resa_reservations as r')->leftJoin('lots_incubation as i', 'i.id', '=', 'r.id_lot_prevu')
            ->when($statut === 'actives', fn ($q) => $q->whereNotIn('r.statut', ['livré', 'annulé']))
            ->when($statut === 'terminees', fn ($q) => $q->whereIn('r.statut', ['livré', 'annulé']))
            ->orderByDesc('r.date_reservation')->orderByDesc('r.id_reservation')->paginate(30, ['r.*', 'i.nom_lot', 'i.date_eclosion_previsionnelle', 'i.date_eclosion'])->withQueryString();

        return view('commercial.reservations.index', compact('previsions', 'aTraiter', 'reservations', 'statut'));
    }

    public function create(Request $request): View
    {
        $previsions = DB::table('resa_lots_previsionnels as p')->join('lots_incubation as i', 'i.id', '=', 'p.id_lot')
            ->whereNull('i.date_eclosion')->whereRaw('COALESCE(i.nombre_eclos,0) = 0')->where('p.nombre_prevu', '>', 0)
            ->orderBy('p.date_eclosion_prevue')->get(['p.*', 'i.nom_lot']);

        return view('commercial.reservations.form', ['previsions' => $previsions, 'choisi' => $request->integer('lot') ?: null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'id_lot_prevu' => ['required', 'integer'], 'nom_client' => ['required', 'string', 'max:100'], 'telephone' => ['required', 'string', 'max:20'],
            'quantite' => ['required', 'integer', 'min:1'], 'prix_unitaire' => ['required', 'string', 'max:20'], 'acompte' => ['nullable', 'string', 'max:20'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ], [], ['id_lot_prevu' => 'lot prévu', 'nom_client' => 'client', 'prix_unitaire' => 'prix unitaire']);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Reservations::reserver($d);
        });

        return redirect()->route('reservations.show', $id)->with('succes', 'Réservation enregistrée.');
    }

    public function show(int $id): View
    {
        $r = DB::table('resa_reservations as r')->leftJoin('lots_incubation as i', 'i.id', '=', 'r.id_lot_prevu')
            ->where('r.id_reservation', $id)->first(['r.*', 'i.nom_lot', 'i.espece', 'i.date_eclosion_previsionnelle', 'i.date_eclosion']) ?? abort(404);
        $lot = DB::table('modLotVol_lots_volailles')->where('id_lot_incubation_source', $r->id_lot_prevu)->first();

        return view('commercial.reservations.show', [
            'r' => $r, 'lotVolaille' => $lot,
            'origines' => $lot ? \App\Support\Lieux::origines(\App\Support\Lieux::VOLAILLE, $lot->id, (int) $lot->effectif_en_cours) : [],
            'produits' => collect(Ventes::produits())->filter(fn ($p) => $p['general']), 'moyens' => Tresorerie::moyens(),
        ]);
    }

    public function traiter(int $incubation): RedirectResponse
    {
        $r = Reservations::traiter($incubation);

        return back()->with('succes', "{$r['servis']} poussin(s) affecté(s) : {$r['confirmees']} réservation(s) confirmée(s), {$r['partielles']} partiellement.");
    }

    public function annuler(Request $request, int $id): RedirectResponse
    {
        Reservations::annuler($id, (string) $request->validate(['motif' => ['required', 'string', 'max:500']])['motif']);

        return back()->with('succes', 'Réservation annulée : la quantité est de nouveau disponible.');
    }

    public function livrer(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate([
            'date_livraison' => ['required', 'date', 'before_or_equal:today'], 'creer_vente' => ['nullable', 'boolean'],
            'produit' => ['nullable', 'required_if:creer_vente,1', 'string'], 'encaisser_acompte' => ['nullable', 'boolean'],
            'moyen_paiement' => ['nullable', Rule::in(Tresorerie::moyens())], 'origine' => ['nullable', 'integer', 'min:0'],
        ]);
        abort_if(! empty($d['creer_vente']) && ! $request->user()->can('VEN.gerer'), 403);
        abort_if(! empty($d['encaisser_acompte']) && ! $request->user()->can('CPT.encaisser'), 403);
        $vente = Reservations::livrer($id, $d);

        return $vente
            ? redirect()->route('ventes.show', $vente)->with('succes', 'Livraison enregistrée et vente créée.')
            : back()->with('succes', 'Livraison enregistrée (aucune vente créée).');
    }
}

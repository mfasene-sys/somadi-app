<?php

namespace App\Http\Controllers\Organisation;

use App\Domaine\Organisation\Rh;
use App\Http\Controllers\Controller;
use App\Support\Requete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RhController extends Controller
{
    public function employes(Request $request): View
    {
        $annee = (int) $request->query('annee', today()->year);
        $employes = DB::table('rh_employes as e')->leftJoin('OVINS_sites as s', 's.id_site', '=', 'e.site_id')->orderByDesc('e.actif')->orderBy('e.nom')->get(['e.*', 's.nom_site']);
        $employes->each(fn ($e) => $e->solde = Rh::solde((int) $e->id, $annee));

        return view('organisation.rh.employes', ['employes' => $employes, 'annee' => $annee, 'aValider' => DB::table('rh_absences')->where('statut', 'soumise')->count()]);
    }

    public function create(): View
    {
        return view('organisation.rh.employe-form', ['e' => null, 'sites' => DB::table('OVINS_sites')->orderBy('nom_site')->get()]);
    }

    private function valider(Request $request): array
    {
        return $request->validate(['matricule' => ['nullable', 'string', 'max:30'], 'nom' => ['required', 'string', 'max:80'], 'prenom' => ['nullable', 'string', 'max:80'],
            'telephone' => ['nullable', 'string', 'max:30'], 'poste' => ['nullable', 'string', 'max:80'], 'site_id' => ['nullable', 'integer'], 'date_entree' => ['nullable', 'date'],
            'salaire_mensuel' => ['required', 'string', 'max:20'], 'actif' => ['nullable', 'boolean']]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->valider($request);
        $id = DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);

            return Rh::enregistrerEmploye(null, $d);
        });

        return redirect()->route('rh.employes.show', $id)->with('succes', 'Employé créé, avec ses droits de l’année.');
    }

    public function show(Request $request, int $id): View
    {
        $e = DB::table('rh_employes')->where('id', $id)->first() ?? abort(404);
        $annee = (int) $request->query('annee', today()->year);

        return view('organisation.rh.employe', [
            'e' => $e, 'annee' => $annee, 's' => Rh::solde($id, $annee), 'taux' => Rh::taux($e),
            'absences' => DB::table('rh_absences')->where('employe_id', $id)->orderByDesc('date_debut')->limit(50)->get(),
            'mouvements' => DB::table('rh_conges_mouvements')->where('employe_id', $id)->where('annee', $annee)->orderByDesc('date_mvt')->orderByDesc('id')->get(),
        ]);
    }

    public function edit(int $id): View
    {
        return view('organisation.rh.employe-form', ['e' => DB::table('rh_employes')->where('id', $id)->first() ?? abort(404), 'sites' => DB::table('OVINS_sites')->orderBy('nom_site')->get()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Rh::enregistrerEmploye($id, $this->valider($request));

        return redirect()->route('rh.employes.show', $id)->with('succes', 'Fiche mise à jour.');
    }

    public function droits(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['annee' => ['required', 'integer', 'min:2000', 'max:2099'], 'quota_libre' => ['required', 'integer', 'min:0', 'max:366'], 'bonus' => ['required', 'integer', 'min:-366', 'max:366'],
            'quota_impose' => ['required', 'integer', 'min:0', 'max:366'], 'note_bonus' => ['nullable', 'string', 'max:255']]);
        Rh::modifierDroits($id, (int) $d['annee'], $d);

        return back()->with('succes', 'Droits mis à jour.');
    }

    public function corriger(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('RH.cloturer'), 403);
        $d = $request->validate(['annee' => ['required', 'integer'], 'type' => ['required', Rule::in(['PRISE', 'PAIEMENT', 'RETENUE'])], 'jours' => ['required', 'integer', 'not_in:0'], 'motif' => ['required', 'string', 'max:1000']]);
        Rh::corriger($id, (int) $d['annee'], $d['type'], (int) $d['jours'], $d['motif']);

        return back()->with('succes', 'Correction enregistrée.');
    }

    public function absences(Request $request): View
    {
        $f = $request->validate(['statut' => ['nullable', Rule::in(array_keys(Rh::STATUTS))], 'employe' => ['nullable', 'integer']]);

        return view('organisation.rh.absences', [
            'f' => $f,
            'absences' => DB::table('rh_absences as a')->join('rh_employes as e', 'e.id', '=', 'a.employe_id')
                ->when($f['statut'] ?? null, fn ($q, $s) => $q->where('a.statut', $s))->when($f['employe'] ?? null, fn ($q, $e) => $q->where('a.employe_id', $e))
                ->orderByRaw("a.statut = 'soumise' DESC")->orderByDesc('a.date_debut')->paginate(50, ['a.*', 'e.nom', 'e.prenom', 'e.matricule'])->withQueryString(),
            'employes' => DB::table('rh_employes')->where('actif', 1)->orderBy('nom')->get(),
        ]);
    }

    public function demander(Request $request): RedirectResponse
    {
        $d = $request->validate(['employe_id' => ['required', 'integer'], 'type' => ['required', Rule::in(array_keys(Rh::TYPES))], 'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date'], 'motif' => ['nullable', 'string', 'max:255']], [], ['employe_id' => 'employé']);
        DB::transaction(function () use ($request, $d) {
            Requete::consommer($request);
            Rh::demander($d);
        });

        return back()->with('succes', 'Demande enregistrée, en attente de validation.');
    }

    public function traiter(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['decision' => ['required', Rule::in(['validee', 'refusee', 'annulee'])], 'commentaire' => ['nullable', 'string', 'max:200']]);
        Rh::traiter($id, $d['decision'], $d['commentaire'] ?? null);

        return back()->with('succes', ['validee' => 'Demande validée.', 'refusee' => 'Demande refusée.', 'annulee' => 'Demande annulée.'][$d['decision']]);
    }

    public function jours(Request $request): View
    {
        $annee = (int) $request->query('annee', today()->year);

        return view('organisation.rh.jours', ['annee' => $annee, 'jours' => Rh::joursImposes($annee)]);
    }

    public function ajouterJour(Request $request): RedirectResponse
    {
        $d = $request->validate(['date_jour' => ['required', 'date'], 'libelle' => ['required', Rule::in(['Korité', 'Tabaski', 'Autre'])]]);
        Rh::ajouterJourImpose($d['date_jour'], $d['libelle']);

        return back()->with('succes', 'Jour ajouté.');
    }

    public function basculerJour(int $id): RedirectResponse
    {
        Rh::basculerJourImpose($id);

        return back()->with('succes', 'Jour mis à jour.');
    }

    public function retenues(Request $request): View
    {
        $annee = (int) $request->query('annee', today()->year);

        return view('organisation.rh.retenues', ['annee' => $annee, 'apercu' => Rh::retenues($annee, false),
            'historique' => DB::table('rh_conges_mouvements as m')->join('rh_employes as e', 'e.id', '=', 'm.employe_id')->where('m.type_mvt', 'RETENUE')->where('m.annee', $annee)
                ->orderByDesc('m.id')->get(['m.*', 'e.nom', 'e.prenom'])]);
    }

    public function appliquerRetenues(Request $request): RedirectResponse
    {
        $annee = (int) $request->validate(['annee' => ['required', 'integer']])['annee'];
        $n = count(Rh::retenues($annee, true));

        return back()->with('succes', "$n retenue(s) générée(s).");
    }

    public function cloture(Request $request): View
    {
        $annee = (int) $request->query('annee', today()->year - (today()->month < 12 ? 1 : 0));

        return view('organisation.rh.cloture', ['annee' => $annee, 'apercu' => Rh::cloture($annee, false)]);
    }

    public function cloturer(Request $request): RedirectResponse
    {
        $d = $request->validate(['annee' => ['required', 'integer'], 'confirmation' => ['accepted']], ['confirmation.accepted' => 'Cochez la case de confirmation.']);
        $r = collect(Rh::cloture((int) $d['annee'], true));

        return back()->with('succes', $r->where('deja', false)->count().' paiement(s) de jours non pris généré(s) ; '.$r->where('deja', true)->count().' déjà payé(s), non repris.');
    }
}

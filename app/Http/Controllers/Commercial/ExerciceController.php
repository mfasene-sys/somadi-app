<?php

namespace App\Http\Controllers\Commercial;

use App\Domaine\Exercices;
use App\Exceptions\RegleMetier;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExerciceController extends Controller
{
    public function index(Request $request): View
    {
        $annee = (int) ($request->validate(['annee' => ['nullable', 'integer', 'min:1900', 'max:2099']])['annee'] ?? today()->year);
        $archivee = DB::table('modCompta_exercices')->where('annee', $annee)->exists();

        return view('commercial.exercices.index', [
            'annee' => $annee,
            'bilan' => $archivee ? null : Exercices::bilan($annee),
            'controles' => $archivee ? null : Exercices::controles(min("$annee-12-31", today()->toDateString()), $annee),
            'archives' => DB::table('modCompta_exercices')->orderByDesc('annee')->get(['annee', 'cutoff', 'closed_at', 'author_name', 'version_application']),
            'borne' => Exercices::borneCloture(),
            'archivee' => $archivee,
            'cloturable' => ! $archivee && $annee < (int) gmdate('Y') && (! Exercices::borneCloture() || "$annee-12-31" > Exercices::borneCloture()),
        ]);
    }

    public function apercu(int $annee): RedirectResponse
    {
        $ap = Exercices::apercu($annee);
        session(["cloture.$annee" => ['revision' => $ap['revision'], 'empreinte' => $ap['empreinte'], 'auteur' => auth()->id()]]);

        return redirect()->route('exercices.cloture', $annee);
    }

    public function cloture(int $annee): View|RedirectResponse
    {
        $s = session("cloture.$annee");
        if (! $s || $s['auteur'] !== auth()->id()) {
            return redirect()->route('exercices.index', ['annee' => $annee])->with('erreur', 'Préparez d’abord l’aperçu de clôture.');
        }
        Exercices::verifierClotureAutorisee($annee);
        $rapport = Exercices::rapport($annee);
        $perime = hash('sha256', json_encode($rapport)) !== $s['empreinte'];

        return view('commercial.exercices.cloture', ['annee' => $annee, 'r' => $rapport, 'jeton' => $s, 'perime' => $perime]);
    }

    public function cloturer(Request $request, int $annee): RedirectResponse
    {
        $d = $request->validate(['commentaire' => ['required', 'string', 'max:4000'], 'revision' => ['required', 'string'], 'empreinte' => ['required', 'string'], 'confirmation' => ['accepted']],
            ['confirmation.accepted' => 'Cochez la case de confirmation.']);
        Exercices::cloturer($annee, $d['commentaire'], $d['revision'], $d['empreinte']);
        session()->forget("cloture.$annee");

        return redirect()->route('exercices.archive', $annee)->with('succes', "Exercice $annee clôturé : l’archive définitive est enregistrée.");
    }

    public function archive(int $annee): View
    {
        try {
            $a = Exercices::archive($annee);
        } catch (RegleMetier) {
            abort(404);
        }

        return view('commercial.exercices.archive', ['annee' => $annee, 'a' => $a]);
    }

    public function pdf(int $annee): Response
    {
        $a = Exercices::archive($annee);
        abort_unless($a['integre'], 409, 'Archive altérée : distribution refusée.');

        return response($a['ligne']->pdf_blob, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="SOMADI-bilan-'.$annee.'.pdf"',
        ]);
    }
}

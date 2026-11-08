<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Droits;
use App\Support\Identite;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Changement d'identité (réservé à l'administrateur, action ACC.identite).
 * Les routes ne passent pas par « can: » : sous une autre identité, l'utilisateur connecté n'est plus admin ;
 * c'est l'administrateur réel, gardé en session, qui est contrôlé.
 */
class IdentiteController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $this->administrateur($request);

        return view('identite.index', [
            'admin' => $admin,
            'cibles' => Identite::cibles($admin)->groupBy(fn (User $u) => $u->roleCanonique()),
            'actuel' => Identite::enCours($request) ? $request->user() : null,
        ]);
    }

    public function prendre(Request $request, User $utilisateur): RedirectResponse
    {
        $admin = $this->administrateur($request);

        if ($utilisateur->id === $admin->id) {
            return $this->revenir($request);
        }
        if ($motif = Identite::refus($admin, $utilisateur)) {
            Journal::enregistrer('identite.refusee', 'ACC', 'utilisateur', $utilisateur->id, apres: ['motif' => $motif]);

            return back()->with('erreur', $motif);
        }

        Identite::prendre($request, $admin, $utilisateur);

        return redirect()->route('accueil')
            ->with('succes', "Vous agissez maintenant en tant que {$utilisateur->nomComplet()}. Vos saisies sont enregistrées à son nom et tracées comme faites par vous.")
            ->with('vider_cache_hors_ligne', true);
    }

    public function revenir(Request $request): RedirectResponse
    {
        if (! Identite::enCours($request)) {
            return redirect()->route('accueil');
        }
        $admin = Identite::revenir($request);
        if (! $admin) {
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Session fermée : le compte administrateur n’est plus actif.']);
        }

        return redirect()->route('identite.index')
            ->with('succes', 'Vous êtes revenu à votre propre compte.')
            ->with('vider_cache_hors_ligne', true);
    }

    private function administrateur(Request $request): User
    {
        $admin = Identite::administrateurReel($request);
        abort_unless($admin && Droits::autorise($admin, 'ACC.identite'), 403);

        return $admin;
    }
}

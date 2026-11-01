<?php

namespace App\Http\Controllers;

use App\Support\Droits;
use App\Support\Journal;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Matrice rôle × action (EC02). L'administrateur a tous les droits et n'apparaît pas dans la matrice. */
class DroitsController extends Controller
{
    public function edit(): View
    {
        $roles = array_filter(Roles::tous(), fn ($r) => $r !== 'admin', ARRAY_FILTER_USE_KEY);
        $matrice = [];
        foreach (array_keys($roles) as $role) {
            $matrice[$role] = Droits::permissionsDuRole($role);
        }

        return view('droits.edit', [
            'roles' => $roles,
            'modules' => Droits::modules(),
            'groupes' => config('somadi.groups'),
            'matrice' => $matrice,
            'reservees' => Droits::RESERVEES_ADMIN,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['droits' => ['array'], 'droits.*' => ['array'], 'droits.*.*' => ['string']]);
        $saisie = $request->input('droits', []);
        $changements = 0;

        foreach (array_keys(Roles::tous()) as $role) {
            if ($role === 'admin') {
                continue;
            }
            [$ajout, $retrait] = Droits::remplacer($role, array_values($saisie[$role] ?? []));
            if ($ajout || $retrait) {
                $changements += count($ajout) + count($retrait);
                Journal::enregistrer('droits.modification', 'ACC', 'role', $role, ['retirees' => $retrait], ['ajoutees' => $ajout]);
            }
        }

        return redirect()->route('droits.edit')->with('succes', $changements ? "Matrice enregistrée ($changements changement".($changements > 1 ? 's' : '').')' : 'Aucun changement.');
    }
}

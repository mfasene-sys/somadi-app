<?php

namespace App\Http\Controllers\Volailles;

use App\Domaine\Incubation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EquipementController extends Controller
{
    public function index(Request $request): View
    {
        $edition = $request->integer('modifier') ?: null;

        return view('volailles.incubation.equipements', ['equipements' => Incubation::equipements(), 'edition' => $edition]);
    }

    public function store(Request $request): RedirectResponse
    {
        Incubation::enregistrerEquipement(null, $this->valider($request));

        return redirect()->route('equipements.index')->with('succes', 'Équipement ajouté.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Incubation::enregistrerEquipement($id, $this->valider($request));

        return redirect()->route('equipements.index')->with('succes', 'Équipement mis à jour.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:50'],
            'capacite' => ['required', 'integer', 'min:1', 'max:1000000'],
            'type' => ['required', Rule::in(['incubateur', 'eclosoir'])],
            'date_mise_service' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], ['capacite' => 'capacité', 'date_mise_service' => 'mise en service']);
    }
}

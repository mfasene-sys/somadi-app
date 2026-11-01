<?php

namespace App\Http\Controllers;

use App\Domaine\Pilotage;
use App\Support\Menu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccueilController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('accueil', [
            'groupes' => Menu::pour($request->user()),
            'lotLivre' => (int) config('somadi.lot_livre'),
            'i' => Pilotage::indicateurs($request->user()),
        ]);
    }
}

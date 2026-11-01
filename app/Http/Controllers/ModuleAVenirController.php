<?php

namespace App\Http\Controllers;

use App\Support\Droits;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Page d'attente pour les modules des lots non encore livrés. */
class ModuleAVenirController extends Controller
{
    public function __invoke(Request $request, string $code): View
    {
        $code = strtoupper($code);
        $module = Droits::modules()[$code] ?? abort(404);
        abort_unless(Droits::autorise($request->user(), "$code.voir"), 403);

        return view('module-a-venir', ['code' => $code, 'module' => $module]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'module' => ['nullable', 'string', 'max:10'],
            'action' => ['nullable', 'string', 'max:60'],
            'utilisateur' => ['nullable', 'integer'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);

        $q = DB::table('app_journal')->orderByDesc('id');
        if (! empty($f['module'])) {
            $q->where('module', $f['module']);
        }
        if (! empty($f['action'])) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $f['action']).'%');
        }
        if (! empty($f['utilisateur'])) {
            // Un administrateur retrouve aussi ce qu'il a fait sous l'identité d'un autre.
            $q->where(function ($w) use ($f) {
                $w->where('user_id', $f['utilisateur']);
                if (\Illuminate\Support\Facades\Schema::hasColumn('app_journal', 'agi_par_id')) {
                    $w->orWhere('agi_par_id', $f['utilisateur']);
                }
            });
        }
        if (! empty($f['du'])) {
            $q->where('cree_le', '>=', $f['du'].' 00:00:00');
        }
        if (! empty($f['au'])) {
            $q->where('cree_le', '<=', $f['au'].' 23:59:59');
        }

        return view('journal.index', [
            'lignes' => $q->paginate(50)->withQueryString(),
            'filtres' => $f,
            'actions' => DB::table('app_journal')->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}

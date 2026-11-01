@php
    $liens = ['Ventes' => route('ventes.index')];
    if (auth()->user()->can('VEN.gerer')) {
        $liens += ['Nouvelle vente' => route('ventes.create'), 'Vente d’œufs' => route('ventes.oeufs'), 'À compléter' => route('ventes.a_completer')];
    }
@endphp
<x-onglets :liens="$liens"/>

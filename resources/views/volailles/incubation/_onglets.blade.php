@php
    $liens = ['En cours' => route('incubation.index'), 'Terminées' => route('incubation.index', ['vue' => 'terminees'])];
    if (auth()->user()->can('INC.gerer')) {
        $liens['Charger un incubateur'] = route('incubation.create');
    }
    $liens['Équipements'] = route('equipements.index');
@endphp
<x-onglets :liens="$liens"/>

@php($liens = ['Emplacements' => route('organisation.lieux.index'), 'Répartition des sujets' => route('organisation.repartition'), 'Journal' => route('organisation.lieux.journal')])
@can('IFP.voir') @php($liens['Planning'] = route('organisation.planning.index')) @endcan
<x-onglets :liens="$liens"/>

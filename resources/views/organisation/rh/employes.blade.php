@php use App\Support\Montant; @endphp
<x-layouts.app titre="Personnel et congés" :sous-titre="'Soldes de congés '.$annee">
    <x-slot:actions>@can('RH.gerer')<a href="{{ route('rh.employes.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/> Employé</a>@endcan</x-slot:actions>
    @include('organisation.rh._onglets')
    @if ($aValider)<a href="{{ route('rh.absences.index', ['statut' => 'soumise']) }}" class="mb-4 flex items-center gap-3 rounded-lg bg-paille-50 px-4 py-3 text-sm text-paille-800 hover:bg-paille-100"><strong>{{ $aValider }}</strong> demande(s) d’absence à valider.<x-icone nom="chevron-right" class="ml-auto size-4"/></a>@endif
    @if ($employes->isEmpty())
        <x-vide icone="id">Aucun employé.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100">
            @foreach ($employes as $e)
                <a href="{{ route('rh.employes.show', $e->id) }}" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm hover:bg-stone-50 {{ $e->actif ? '' : 'opacity-60' }}">
                    <span class="min-w-0 flex-1"><span class="font-medium text-stone-900">{{ trim($e->prenom.' '.$e->nom) }}</span> <span class="text-xs text-stone-500">{{ $e->matricule }}</span>
                        <span class="block text-xs text-stone-500">{{ $e->poste ?: '—' }}{{ $e->nom_site ? ' · '.$e->nom_site : '' }} · {{ Montant::fcfa(Montant::base($e->salaire_mensuel)) }}/mois</span></span>
                    <span class="text-right tabular-nums"><strong class="{{ $e->solde['reste'] < 0 ? 'text-red-700' : '' }}">{{ $e->solde['reste'] }}</strong> j<span class="block text-xs text-stone-500">{{ $e->solde['pris'] }} pris / {{ $e->solde['quota'] }}</span></span>
                    @if ($e->solde['a_retenir'])<x-statut ton="alerte">{{ $e->solde['a_retenir'] }} j à retenir</x-statut>@endif
                </a>
            @endforeach
        </div>
    @endif
    <p class="mt-3 text-xs text-stone-500">Le personnel est distinct des comptes de connexion : un salarié n’a pas forcément de compte.</p>
</x-layouts.app>

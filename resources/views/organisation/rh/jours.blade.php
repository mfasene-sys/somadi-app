@php use App\Support\Format; @endphp
<x-layouts.app titre="Jours imposés" :sous-titre="'Fêtes non décomptées des congés en '.$annee">
    <x-slot:actions><a href="?annee={{ $annee - 1 }}" class="btn-secondaire">‹ {{ $annee - 1 }}</a><a href="?annee={{ $annee + 1 }}" class="btn-secondaire">{{ $annee + 1 }} ›</a></x-slot:actions>
    @include('organisation.rh._onglets')
    <div class="grid max-w-3xl gap-6 sm:grid-cols-[1fr_16rem]">
        <div class="carte divide-y divide-stone-100 text-sm">
            @forelse ($jours as $j)
                <div class="flex items-center gap-3 px-4 py-2.5 {{ $j->actif ? '' : 'opacity-50' }}"><span class="flex-1">{{ Format::dateLongue($j->date_jour) }} · {{ $j->libelle }}</span>
                    @can('RH.gerer')<form method="POST" action="{{ route('rh.jours.basculer', $j->id) }}">@csrf<button class="text-xs font-semibold text-ferme-700">{{ $j->actif ? 'Désactiver' : 'Réactiver' }}</button></form>@endcan</div>
            @empty <p class="px-4 py-3 text-stone-500">Aucun jour imposé pour {{ $annee }}.</p> @endforelse
        </div>
        @can('RH.gerer')
            <form method="POST" action="{{ route('rh.jours.store') }}" class="carte h-fit space-y-3 p-4">@csrf
                <x-champ name="date_jour" label="Date" type="date" required/>
                <x-choix name="libelle" label="Fête" :options="['Korité' => 'Korité', 'Tabaski' => 'Tabaski', 'Autre' => 'Autre']"/>
                <button class="btn-primaire w-full">Ajouter</button></form>
        @endcan
    </div>
</x-layouts.app>

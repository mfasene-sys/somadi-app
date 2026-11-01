<x-layouts.app titre="Activités" :sous-titre="ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM'))">
    <x-slot:actions>@if (\App\Domaine\Organisation\Activites::planificateur())<a href="{{ route('activites.taches.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/> Tâche</a>@endif</x-slot:actions>
    @include('organisation.activites._onglets')
    @if ($retard)<p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>{{ $retard }}</strong> tâche(s) en retard sur leur échéance. <a href="{{ route('activites.taches.index', ['statut' => 'planifie']) }}" class="underline">Voir</a></p>@endif
    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Mes tâches <span class="font-normal text-stone-500">· 7 prochains jours</span></h2>
            @if ($mes->isEmpty())<x-vide icone="check">Aucune tâche à faire.</x-vide>
            @else <div class="carte divide-y divide-stone-100">@foreach ($mes as $t) @include('organisation.activites._tache') @endforeach</div> @endif
        </section>
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Aujourd’hui à la ferme</h2>
            @if ($jour->isEmpty())<x-vide icone="calendar">Aucune tâche prévue aujourd’hui.</x-vide>
            @else <div class="carte divide-y divide-stone-100">@foreach ($jour as $t) @include('organisation.activites._tache') @endforeach</div> @endif
        </section>
    </div>
</x-layouts.app>

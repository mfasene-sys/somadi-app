@php use App\Domaine\Organisation\Activites as A; @endphp
<x-layouts.app titre="Tâches" sous-titre="Toutes les tâches de votre périmètre">
    <x-slot:actions>@if (A::planificateur())<a href="{{ route('activites.taches.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/> Tâche</a>@endif</x-slot:actions>
    @include('organisation.activites._onglets')
    <form method="GET" class="carte mb-4 grid grid-cols-2 gap-3 p-4 sm:grid-cols-6">
        <x-champ name="q" label="Titre" :value="$f['q'] ?? ''" class="col-span-2"/>
        <x-choix name="statut" label="État" :options="collect(A::STATUTS)->map(fn ($s) => $s[0])->all()" :value="$f['statut'] ?? null" vide="Tous"/>
        <x-choix name="projet" label="Projet" :options="$projets->mapWithKeys(fn ($p) => [$p->id => $p->titre])->all()" :value="$f['projet'] ?? null" vide="Tous"/>
        <x-choix name="routine" label="Routine" :options="$routines->mapWithKeys(fn ($r) => [$r->id => $r->titre])->all()" :value="$f['routine'] ?? null" vide="Toutes"/>
        <div class="flex items-end"><button class="btn-secondaire w-full">Filtrer</button></div>
    </form>
    @if ($taches->isEmpty())<x-vide icone="check">Aucune tâche.</x-vide>
    @else <div class="carte divide-y divide-stone-100">@foreach ($taches as $t) @include('organisation.activites._tache') @endforeach</div><div class="mt-3">{{ $taches->links() }}</div> @endif
</x-layouts.app>

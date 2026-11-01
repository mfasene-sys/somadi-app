@php use App\Domaine\Organisation\Activites as A; use App\Support\Format; $peut = A::planificateur(); $statuts = ['planifie' => ['Planifié', 'info'], 'en_cours' => ['En cours', 'attente'], 'termine' => ['Terminé', 'ok'], 'annule' => ['Annulé', 'neutre']]; @endphp
<x-layouts.app titre="Projets">
    @include('organisation.activites._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-3">
            @forelse ($liste as $p)
                <details class="carte">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 p-4">
                        <span class="flex-1"><span class="font-medium">{{ $p->code ? $p->code.' · ' : '' }}{{ $p->titre }}</span><span class="block text-xs text-stone-500">{{ $p->responsable ?: 'Sans responsable' }}{{ $p->date_cible ? ' · cible '.Format::date($p->date_cible) : '' }} · {{ (int) $p->faites }}/{{ (int) $p->n }} tâches réalisées</span></span>
                        <x-statut :ton="$statuts[$p->statut][1]">{{ $statuts[$p->statut][0] }}</x-statut>
                        <a href="{{ route('activites.taches.index', ['projet' => $p->id]) }}" class="text-xs font-semibold text-ferme-700">Tâches</a>
                    </summary>
                    @if ($peut)
                        <form method="POST" action="{{ route('activites.projets.enregistrer', $p->id) }}" class="grid gap-3 border-t border-stone-100 p-4 sm:grid-cols-2">@csrf
                            @include('organisation.activites._projet', ['p' => $p])<button class="btn-secondaire sm:col-span-2">Enregistrer</button></form>
                    @endif
                </details>
            @empty <x-vide icone="layers">Aucun projet.</x-vide> @endforelse
        </div>
        @if ($peut)
            <form method="POST" action="{{ route('activites.projets.enregistrer') }}" class="carte grid h-fit gap-3 p-4">@csrf
                <h2 class="font-semibold text-stone-900">Nouveau projet</h2>
                @include('organisation.activites._projet', ['p' => null])<button class="btn-primaire">Créer</button></form>
        @endif
    </div>
</x-layouts.app>

@php use App\Support\Format; @endphp
<x-layouts.app titre="Journal des emplacements" sous-titre="Entrées, sorties et synchronisations enregistrées par la base">
    @include('organisation.lieux._onglets')
    <div class="carte divide-y divide-stone-100 text-sm">
        @forelse ($journal as $j)
            <div class="flex flex-wrap gap-x-3 px-4 py-2.5"><span class="w-32 tabular-nums text-stone-500">{{ Format::date(substr($j->cree_le, 0, 10)) }} {{ substr($j->cree_le, 11, 5) }}</span>
                <span class="min-w-0 flex-1">{{ $j->nom_batiment ?: '#'.$j->id_batiment }} · {{ $j->action }} · {{ $j->famille }} #{{ $j->id_source }} : {{ $j->avant_qte }} → {{ $j->apres_qte }}<span class="block text-xs text-stone-500">{{ $j->motif }} · {{ $j->acteur }}</span></span></div>
        @empty <p class="px-4 py-3 text-stone-500">Journal vide.</p> @endforelse
    </div>
    <div class="mt-3">{{ $journal->links() }}</div>
</x-layouts.app>

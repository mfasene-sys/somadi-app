@php use App\Support\Format; use App\Domaine\LotsVolailles; @endphp
<x-layouts.app titre="Lots de volailles" sous-titre="Effectifs, mouvements, pesées et soins">
    <x-slot:actions>
        @can('VOL.gerer')<a href="{{ route('lots.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/>Nouveau lot</a>@endcan
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-onglets :liens="['Actifs' => route('lots.index'), 'Archivés' => route('lots.index', ['vue' => 'archives'])]"/>
        <form method="GET" class="relative w-full sm:w-72">
            @if ($vue === 'archives')<input type="hidden" name="vue" value="archives">@endif
            <x-icone nom="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400"/>
            <input type="search" name="q" value="{{ $q }}" placeholder="Rechercher un lot" class="champ pl-9">
        </form>
    </div>

    @if ($lots->isEmpty())
        <x-vide icone="layers">Aucun lot {{ $vue === 'actifs' ? 'actif' : 'archivé' }}{{ $q ? ' pour cette recherche' : '' }}.</x-vide>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($lots as $l)
                <a href="{{ route('lots.show', $l->id) }}" class="carte block p-4 transition hover:border-ferme-200 hover:shadow">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-stone-900">{{ $l->nom_lot }}</div>
                            <div class="text-xs text-stone-500">{{ ucfirst((string) $l->espece) }} · {{ $l->race }}</div>
                        </div>
                        <span class="text-right"><span class="block text-xl font-bold tabular-nums">{{ Format::nombre($l->effectif_en_cours) }}</span><span class="text-xs text-stone-500">sur {{ Format::nombre($l->nombre_initial) }}</span></span>
                    </div>
                    <div class="mt-3 text-xs text-stone-500">Entré le {{ Format::date($l->date_entree) }}@php $age = LotsVolailles::age($l); @endphp @if($age !== null && $age >= 0) · {{ $age }} jours @endif</div>
                </a>
            @endforeach
        </div>
        <div class="mt-3">{{ $lots->links() }}</div>
    @endif
</x-layouts.app>

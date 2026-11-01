@php use App\Support\Format; @endphp
<x-layouts.app titre="Pesées et rentabilité" sous-titre="Marge des lots actifs selon les coûts de référence par âge">
    <x-onglets :liens="['Lots' => route('rentabilite.index'), 'Coûts de référence' => route('references.index')]"/>

    @php [$inclus, $exclus] = $lots->partition(fn ($l) => ! $l->exclu_analyses); @endphp
    @if ($inclus->isEmpty())
        <x-vide icone="scale">Aucun lot actif à analyser.</x-vide>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($inclus as $l)
                <a href="{{ route('rentabilite.show', $l->id) }}" class="carte flex items-center gap-3 p-4 hover:border-ferme-200">
                    <span class="min-w-0 flex-1"><span class="block truncate font-medium text-stone-900">{{ $l->nom_lot }}</span><span class="text-xs text-stone-500">{{ ucfirst((string) $l->espece) }} · {{ Format::nombre($l->effectif_en_cours) }} sujets</span></span>
                    <x-icone nom="chevron-right" class="size-4 text-stone-400"/>
                </a>
            @endforeach
        </div>
    @endif

    @if ($exclus->isNotEmpty())
        <h2 class="mb-2 mt-8 text-sm font-semibold tracking-wide text-stone-500 uppercase">Exclus des analyses</h2>
        <ul class="carte divide-y divide-stone-100 text-sm">
            @foreach ($exclus as $l)
                <li class="flex flex-wrap items-center gap-2 px-4 py-3"><a href="{{ route('rentabilite.show', $l->id) }}" class="font-medium text-ferme-700 hover:underline">{{ $l->nom_lot }}</a><span class="text-stone-500">— {{ $l->motif_exclusion ?: 'sans motif' }}</span></li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>

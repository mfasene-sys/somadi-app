@php use App\Support\Format; use App\Domaine\Ovins\Referentiels; @endphp
<x-layouts.app titre="Lots ovins" sous-titre="Effectif courant du lot comparé au nombre de fiches individuelles présentes">
    <x-slot:actions>
        @can('OVA.gerer')<a href="{{ route('ovins.lots.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/> Nouveau lot</a>@endcan
    </x-slot:actions>
    @include('ovins._onglets')

    @if ($lots->isEmpty())
        <x-vide icone="layers">Aucun lot.</x-vide>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($lots as $l)
                @php($ecart = (int) $l->effectif_encours - (int) $l->fiches)
                <a href="{{ route('ovins.lots.show', $l->id_lot) }}" class="carte block p-4 hover:border-ferme-200 {{ $l->statut !== 'actif' ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-2"><span class="flex-1 font-medium text-stone-900">{{ $l->nom_lot }}</span>
                        <x-statut :ton="$l->statut === 'actif' ? 'ok' : 'neutre'">{{ $l->statut === 'actif' ? Referentiels::libelleType($l->type_lot) : 'Clos' }}</x-statut></div>
                    <p class="text-xs text-stone-500">{{ $l->nom_site }}{{ $l->race ? ' · '.$l->race : '' }} · depuis le {{ Format::date($l->date_entree) }}</p>
                    <div class="mt-3 flex items-end gap-4">
                        <span><span class="block text-2xl font-bold tabular-nums">{{ Format::nombre($l->effectif_encours) }}</span><span class="text-xs text-stone-500">effectif</span></span>
                        <span><span class="block text-lg font-semibold tabular-nums text-stone-600">{{ Format::nombre($l->fiches) }}</span><span class="text-xs text-stone-500">fiches</span></span>
                        @if ($ecart !== 0)<x-statut ton="attente" class="ml-auto">écart {{ $ecart > 0 ? '+' : '' }}{{ $ecart }}</x-statut>@endif
                    </div>
                </a>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-stone-500">Un écart signifie que des animaux du lot n’ont pas de fiche individuelle, ou que l’effectif n’a pas suivi un mouvement. Il se corrige depuis la fiche du lot, avec un motif.</p>
    @endif
</x-layouts.app>

@php use App\Support\Format; @endphp
<x-layouts.app titre="Inventaires ovins" sous-titre="Comptage physique du troupeau à partir d’une liste figée">
    <x-slot:actions>@can('OVI.gerer')<a href="{{ route('ovins.inventaires.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/> Ouvrir un inventaire</a>@endcan</x-slot:actions>
    @if ($inventaires->isEmpty())
        <x-vide icone="clipboard">Aucun inventaire.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100">
            @foreach ($inventaires as $i)
                <a href="{{ route('ovins.inventaires.show', $i->id) }}" class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-stone-50">
                    <span class="flex-1"><span class="font-medium text-stone-900">{{ $i->titre }}</span><span class="block text-xs text-stone-500">{{ $i->perimetre }} · ouvert le {{ Format::date(substr($i->ouvert_le, 0, 10)) }} par {{ $i->ouvert_par }}</span></span>
                    <span class="text-right tabular-nums"><strong>{{ (int) $i->pointes }}</strong> / {{ $i->total_attendu }}<span class="block text-xs text-stone-500">{{ (int) $i->supp ? $i->supp.' supplémentaire(s)' : 'pointés' }}</span></span>
                    <x-statut :ton="$i->statut === 'ouvert' ? 'attente' : 'ok'">{{ $i->statut === 'ouvert' ? 'Ouvert' : 'Clôturé' }}</x-statut>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>

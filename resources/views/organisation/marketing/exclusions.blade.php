@php use App\Support\Format; @endphp
<x-layouts.app titre="Liste d’exclusion" sous-titre="Numéros qui ne veulent plus recevoir de messages">
    @include('organisation.marketing._onglets')
    <div class="grid max-w-3xl gap-6 sm:grid-cols-[1fr_18rem]">
        <div class="carte divide-y divide-stone-100 text-sm">
            @forelse ($liste as $l)
                <div class="flex items-center gap-3 px-4 py-2.5"><span class="flex-1 tabular-nums">{{ $l->client_tel_norm }}<span class="block text-xs text-stone-500">{{ $l->motif }} · {{ Format::date(substr($l->created_at, 0, 10)) }}</span></span>
                    @can('MKT.gerer')<form method="POST" action="{{ route('marketing.exclusions.retirer', $l->id) }}" onsubmit="return confirm('Retirer ce numéro ?')">@csrf<button class="text-xs font-semibold text-red-700">Retirer</button></form>@endcan</div>
            @empty <p class="px-4 py-3 text-stone-500">Liste vide.</p> @endforelse
        </div>
        @can('MKT.gerer')
            <form method="POST" action="{{ route('marketing.exclusions.store') }}" class="carte h-fit space-y-3 p-4">@csrf
                <x-champ name="tel" label="Téléphone" type="tel" required placeholder="77 123 45 67"/>
                <x-champ name="motif" label="Motif" maxlength="150"/>
                <button class="btn-primaire w-full">Exclure</button></form>
        @endcan
    </div>
</x-layouts.app>

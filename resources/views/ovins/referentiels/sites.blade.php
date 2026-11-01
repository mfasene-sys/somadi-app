@php use App\Support\Empreinte; use App\Support\Format; @endphp
<x-layouts.app titre="Sites ovins" sous-titre="Lieux d’élevage du troupeau">
    @include('ovins._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-3">
            @forelse ($sites as $s)
                <details class="carte group {{ $s->actif ? '' : 'opacity-60' }}">
                    <summary class="flex cursor-pointer list-none items-center gap-3 p-4">
                        <span class="rounded bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">{{ $s->code_site }}</span>
                        <span class="flex-1 font-medium">{{ $s->nom_site }}<span class="block text-xs font-normal text-stone-500">{{ $s->localisation }}{{ $s->type_site ? ' · '.$s->type_site : '' }}</span></span>
                        <span class="text-right text-sm tabular-nums"><strong>{{ $presents[$s->id_site] ?? 0 }}</strong><span class="text-stone-500"> / {{ Format::nombre($s->capacite_ovins) }}</span></span>
                    </summary>
                    @can('OVA.gerer')
                        <form method="POST" action="{{ route('ovins.sites.enregistrer', $s->id_site) }}" class="grid gap-3 border-t border-stone-100 p-4 sm:grid-cols-2">
                            @csrf <input type="hidden" name="empreinte" value="{{ Empreinte::de($s) }}">
                            @include('ovins.referentiels._site', ['s' => $s, 'p' => 's'.$s->id_site])
                            <button class="btn-secondaire sm:col-span-2">Enregistrer</button>
                        </form>
                    @endcan
                </details>
            @empty <x-vide icone="building">Aucun site.</x-vide> @endforelse
            <p class="text-xs text-stone-500">La capacité indiquée est celle du site ; elle n’est pas reprise comme capacité des parcs (module Infrastructures).</p>
        </div>
        @can('OVA.gerer')
            <form method="POST" action="{{ route('ovins.sites.enregistrer') }}" class="carte grid h-fit gap-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Nouveau site</h2>
                @include('ovins.referentiels._site', ['s' => null, 'p' => 'n'])
                <button class="btn-primaire">Créer</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

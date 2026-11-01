@php use App\Support\Empreinte; @endphp
<x-layouts.app titre="Propriétaires" sous-titre="Associés et propriétaires des animaux ; la couleur sert au repérage dans les listes">
    @include('ovins._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-3">
            @forelse ($proprietaires as $p)
                <details class="carte {{ $p->actif ? '' : 'opacity-60' }}">
                    <summary class="flex cursor-pointer list-none items-center gap-3 p-4">
                        <span class="size-4 rounded-full ring-1 ring-stone-200" style="background: {{ $p->couleur_hex ?: '#e7e5e4' }}"></span>
                        <span class="flex-1 font-medium">{{ $p->nom_proprietaire }}<span class="block text-xs font-normal text-stone-500">{{ $p->telephone }}</span></span>
                        <span class="text-sm tabular-nums"><strong>{{ $presents[$p->id_proprietaire] ?? 0 }}</strong> <span class="text-stone-500">animaux</span></span>
                    </summary>
                    @can('OVA.gerer')
                        <form method="POST" action="{{ route('ovins.proprietaires.enregistrer', $p->id_proprietaire) }}" class="grid gap-3 border-t border-stone-100 p-4 sm:grid-cols-2">
                            @csrf <input type="hidden" name="empreinte" value="{{ Empreinte::de($p) }}">
                            @include('ovins.referentiels._proprietaire', ['p' => $p, 'x' => 'p'.$p->id_proprietaire])
                            <button class="btn-secondaire sm:col-span-2">Enregistrer</button>
                        </form>
                    @endcan
                </details>
            @empty <x-vide icone="users">Aucun propriétaire.</x-vide> @endforelse
        </div>
        @can('OVA.gerer')
            <form method="POST" action="{{ route('ovins.proprietaires.enregistrer') }}" class="carte grid h-fit gap-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Nouveau propriétaire</h2>
                @include('ovins.referentiels._proprietaire', ['p' => null, 'x' => 'n'])
                <button class="btn-primaire">Créer</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

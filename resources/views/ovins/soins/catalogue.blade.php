<x-layouts.app titre="Catalogue et modèles" sous-titre="Soins disponibles et protocoles réutilisables (J0, J+7…)">
    <x-onglets :liens="['À faire' => route('ovins.soins.index'), 'Historique' => route('ovins.soins.index', ['vue' => 'historique']), 'Catalogue et modèles' => route('ovins.soins.catalogue')]"/>
    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <div class="mb-3 flex items-center"><h2 class="flex-1 font-semibold text-stone-900">Modèles</h2>
                @can('OVS.gerer')<a href="{{ route('ovins.soins.modele.create') }}" class="btn-secondaire min-h-9"><x-icone nom="plus" class="size-4"/> Modèle</a>@endcan</div>
            <div class="carte divide-y divide-stone-100 text-sm">
                @forelse ($modeles as $m)
                    <a href="{{ route('ovins.soins.modele', $m->id_modele) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50 {{ $m->actif ? '' : 'opacity-60' }}">
                        <span class="flex-1"><span class="font-medium">{{ $m->nom_modele }}</span><span class="block text-xs text-stone-500">{{ $m->categorie }} · {{ ['tous' => 'lots et animaux', 'lot' => 'lots', 'animal' => 'animaux'][$m->cible] ?? $m->cible }} · {{ $m->lignes }} soin(s)</span></span>
                        <x-statut :ton="$m->actif ? 'ok' : 'neutre'">{{ $m->actif ? 'Actif' : 'Inactif' }}</x-statut>
                    </a>
                @empty <p class="px-4 py-3 text-stone-500">Aucun modèle.</p> @endforelse
            </div>
        </section>
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Soins du catalogue</h2>
            <div class="space-y-2">
                @foreach ($types as $t)
                    <details class="carte">
                        <summary class="flex cursor-pointer list-none gap-3 px-4 py-3 text-sm"><span class="flex-1 font-medium">{{ $t->nom_soin }}</span><span class="text-xs text-stone-500">{{ $t->categorie }}{{ $t->voie ? ' · '.$t->voie : '' }}{{ $t->delai_attente_jours ? ' · attente '.$t->delai_attente_jours.' j' : '' }}</span></summary>
                        @can('OVS.gerer')
                            <form method="POST" action="{{ route('ovins.soins.types', $t->id_type_soin) }}" class="grid gap-3 border-t border-stone-100 p-4 sm:grid-cols-2">@csrf
                                @include('ovins.soins._type', ['t' => $t, 'x' => 't'.$t->id_type_soin])
                                <button class="btn-secondaire sm:col-span-2">Enregistrer</button></form>
                        @endcan
                    </details>
                @endforeach
                @can('OVS.gerer')
                    <form method="POST" action="{{ route('ovins.soins.types') }}" class="carte grid gap-3 p-4 sm:grid-cols-2">@csrf
                        <h3 class="font-semibold text-stone-900 sm:col-span-2">Nouveau soin</h3>
                        @include('ovins.soins._type', ['t' => null, 'x' => 'n'])
                        <button class="btn-primaire sm:col-span-2">Ajouter</button></form>
                @endcan
            </div>
            <p class="mt-3 text-xs text-stone-500">Le délai d’attente est indiqué pour information ; il ne bloque pas une vente.</p>
        </section>
    </div>
</x-layouts.app>

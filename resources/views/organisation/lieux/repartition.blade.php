@php use App\Domaine\Organisation\Infrastructures as I; use App\Support\Format; @endphp
<x-layouts.app titre="Répartition des sujets" sous-titre="Placer les sujets dans les emplacements compatibles, sans dépasser l’effectif ni la capacité">
    @include('organisation.lieux._onglets')
    <div class="mb-4 flex flex-wrap gap-1 rounded-xl bg-stone-100 p-1 sm:w-fit">
        @foreach (I::SOURCES as $k => [, $lib])
            <a href="?famille={{ $k }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ $famille === $k ? 'bg-white text-ferme-800 shadow-sm' : 'text-stone-600' }}">{{ $lib }}</a>
        @endforeach
    </div>
    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        <div class="carte min-w-0 divide-y divide-stone-100 text-sm">
            @forelse ($sources as $s)
                <a href="?famille={{ $famille }}&source={{ $s->id }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-stone-50 {{ $source && $source->id === $s->id ? 'bg-ferme-50' : '' }}">
                    <span class="flex-1 font-medium">{{ $s->nom }} <span class="font-normal text-stone-500">{{ $s->espece }}{{ isset($s->nom_site) ? ' · '.$s->nom_site : '' }}</span></span>
                    <span class="text-right text-xs tabular-nums"><strong class="text-sm">{{ $s->place }}</strong> / {{ $s->effectif }} placés
                        @if ($s->non_affecte)<span class="block text-paille-700">{{ $s->non_affecte }} non affecté(s)</span>@endif</span>
                </a>
            @empty <p class="px-4 py-3 text-stone-500">Aucune source active.</p> @endforelse
        </div>
        <aside>
            @if ($source)
                <div class="carte space-y-3 p-4">
                    <h2 class="font-semibold text-stone-900">{{ $source->nom }}</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($positions as $p)<li class="flex justify-between"><span>{{ $p->code ?: $p->nom_batiment }}</span><strong class="tabular-nums">{{ $p->quantite }}</strong></li>@endforeach
                        <li class="flex justify-between text-stone-500"><span>Non affecté</span><span class="tabular-nums">{{ $source->non_affecte }}</span></li>
                    </ul>
                    @can('INF.gerer')
                        <form method="POST" action="{{ route('organisation.repartition.deplacer') }}" class="space-y-3 border-t border-stone-100 pt-3">
                            @csrf <x-cle/>
                            <input type="hidden" name="famille" value="{{ $famille }}"><input type="hidden" name="source" value="{{ $source->id }}"><input type="hidden" name="empreinte" value="{{ $empreinte }}">
                            <x-choix name="origine" label="Depuis" :options="['0' => 'Non affectés ('.$source->non_affecte.')'] + $positions->mapWithKeys(fn ($p) => [$p->id_batiment => ($p->code ?: $p->nom_batiment).' ('.$p->quantite.')'])->all()" :value="$source->non_affecte ? '0' : null"/>
                            <x-choix name="destination" label="Vers" :options="$destinations->mapWithKeys(fn ($l) => [$l->id => $l->code.' · '.$l->nom_batiment.' (libre '.max(0, $l->capacite - $l->occupation).')'])->all() + ['0' => 'Retirer : non affectés']" vide="Choisir…" required/>
                            <x-champ name="quantite" label="Quantité" type="number" min="1" :value="$famille === 'ovin' ? 1 : $source->non_affecte" required/>
                            <x-champ name="motif" label="Motif" required maxlength="500" placeholder="Mise en place, changement de bâtiment…"/>
                            <button class="btn-primaire w-full">Déplacer</button>
                            <p class="text-xs text-stone-500">Seuls les emplacements disponibles, actifs et compatibles (famille, espèce{{ $famille === 'ovin' ? ', site de l’animal' : '' }}) sont proposés.</p>
                        </form>
                    @endcan
                </div>
            @else
                <p class="carte p-4 text-sm text-stone-500">Choisissez une source pour voir et modifier sa répartition.</p>
            @endif
        </aside>
    </div>
</x-layouts.app>

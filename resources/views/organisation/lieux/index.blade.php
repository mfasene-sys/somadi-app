@php use App\Domaine\Organisation\Infrastructures as I; use App\Support\Format; $groupes = $lieux->groupBy(fn ($l) => $l->nom_site ?: 'À configurer'); @endphp
<x-layouts.app titre="Bâtiments et parcs" sous-titre="Emplacements, occupation et capacité restante">
    @include('organisation.lieux._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            @forelse ($groupes as $site => $liste)
                <section>
                    <h2 class="mb-3 font-semibold text-stone-900">{{ $site }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($liste as $l)
                            @php($pct = $l->capacite > 0 ? min(100, round($l->occupation / $l->capacite * 100)) : 0)
                            @php([$etat, $ton] = I::ETATS[$l->etat] ?? [$l->code ? $l->etat : 'À configurer', 'neutre'])
                            <a href="{{ route('organisation.lieux.show', $l->id) }}" class="carte block p-4 hover:border-ferme-200 {{ $l->actif ? '' : 'opacity-60' }}">
                                <div class="flex items-start gap-2"><span class="flex-1"><span class="font-medium text-stone-900">{{ $l->code ?: '—' }}</span> <span class="text-stone-500">{{ $l->nom_batiment }}</span>
                                    <span class="block text-xs text-stone-500">{{ I::TYPES[$l->type_lieu] ?? '' }} · {{ I::FAMILLES[$l->famille] ?? 'à configurer' }}{{ $l->espece ? ' · '.$l->espece : '' }}{{ $l->stade ? ' · '.$l->stade : '' }}</span></span>
                                    <x-statut :ton="$l->code ? $ton : 'alerte'">{{ $l->code ? $etat : 'À configurer' }}</x-statut></div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full {{ $pct >= 100 ? 'bg-red-600' : ($pct >= 85 ? 'bg-paille-500' : 'bg-ferme-600') }}" style="width: {{ $pct }}%"></div></div>
                                <p class="mt-1 text-xs tabular-nums text-stone-600">{{ Format::nombre($l->occupation) }} / {{ Format::nombre($l->capacite) }} · reste {{ Format::nombre(max(0, $l->capacite - $l->occupation)) }}{{ $l->disponible_le ? ' · disponible le '.Format::date($l->disponible_le) : '' }}</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @empty <x-vide icone="building">Aucun emplacement.</x-vide> @endforelse
        </div>
        @can('INF.gerer')
            <form method="POST" action="{{ route('organisation.lieux.store') }}" class="carte grid h-fit gap-3 p-4">
                @csrf <x-cle/>
                <h2 class="font-semibold text-stone-900">Nouvel emplacement</h2>
                @include('organisation.lieux._form', ['l' => null, 'prefixe' => 'n'])
                <button class="btn-primaire">Créer</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

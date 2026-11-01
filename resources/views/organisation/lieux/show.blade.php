@php use App\Domaine\Organisation\Infrastructures as I; use App\Domaine\Organisation\Planning as P; use App\Support\Format; [$etat, $ton] = I::ETATS[$l->etat] ?? ['À configurer', 'alerte']; @endphp
<x-layouts.app :titre="($l->code ?: 'Emplacement').' · '.$l->nom_batiment" :sous-titre="($l->nom_site ?: 'Site non défini').' · '.(I::TYPES[$l->type_lieu] ?? '').' · '.(I::FAMILLES[$l->famille] ?? 'à configurer')">
    <x-slot:actions><x-statut :ton="$ton" class="px-3 py-1 text-sm">{{ $l->code ? $etat : 'À configurer' }}</x-statut></x-slot:actions>
    @include('organisation.lieux._onglets')
    <div class="mb-6 grid grid-cols-3 gap-3">
        <x-indicateur label="Occupation" :valeur="Format::nombre($l->occupation)"/>
        <x-indicateur label="Capacité" :valeur="Format::nombre($l->capacite)"/>
        <x-indicateur label="Places libres" :valeur="Format::nombre(max(0, $l->capacite - $l->occupation))"/>
    </div>
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Sujets présents</h2>
                <div class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($occupants as $o)
                        <a href="{{ route('organisation.repartition', ['famille' => $o->famille, 'source' => $o->id_source]) }}" class="flex gap-3 px-4 py-2.5 hover:bg-stone-50"><span class="flex-1">{{ I::SOURCES[$o->famille][1] ?? $o->famille }} · <strong>{{ $o->nom }}</strong></span><span class="tabular-nums">{{ $o->quantite }}</span></a>
                    @empty <p class="px-4 py-3 text-stone-500">Emplacement vide.</p> @endforelse
                </div>
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Planning</h2>
                <div class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($periodes as $p)
                        <div class="flex flex-wrap gap-3 px-4 py-2.5"><span class="tabular-nums text-stone-500">{{ Format::date($p->date_debut) }} → {{ Format::date($p->date_fin) }}</span><span class="flex-1">{{ P::NATURES[$p->nature] }} · {{ $p->libelle }}{{ $p->quantite ? ' · '.$p->quantite.' places' : '' }}</span><x-statut :ton="P::STATUTS[$p->statut][1]">{{ P::STATUTS[$p->statut][0] }}</x-statut></div>
                    @empty <p class="px-4 py-3 text-stone-500">Rien de prévu.</p> @endforelse
                </div>
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Journal</h2>
                <div class="carte divide-y divide-stone-100 text-xs">
                    @forelse ($journal as $j)
                        <div class="flex gap-3 px-4 py-2"><span class="w-32 shrink-0 text-stone-500">{{ Format::date(substr($j->cree_le, 0, 10)) }} {{ substr($j->cree_le, 11, 5) }}</span><span class="flex-1">{{ $j->action }} · {{ $j->famille }} #{{ $j->id_source }} : {{ $j->avant_qte }} → {{ $j->apres_qte }}<span class="block text-stone-500">{{ $j->motif }} · {{ $j->acteur }}</span></span></div>
                    @empty <p class="px-4 py-3 text-stone-500">Aucune entrée.</p> @endforelse
                </div>
            </section>
        </div>
        @can('INF.gerer')
            <form method="POST" action="{{ route('organisation.lieux.update', $l->id) }}" class="carte grid h-fit gap-3 p-4">
                @csrf @method('PUT') <input type="hidden" name="version" value="{{ $l->version }}">
                <h2 class="font-semibold text-stone-900">Configuration</h2>
                @include('organisation.lieux._form', ['l' => $l, 'prefixe' => 'e'])
                <button class="btn-primaire">Enregistrer</button>
                @if ($l->occupation > 0)<p class="text-xs text-stone-500">Emplacement occupé : le site, la famille, l’espèce, le stade et la disponibilité ne peuvent changer qu’une fois vidé.</p>@endif
            </form>
        @endcan
    </div>
</x-layouts.app>

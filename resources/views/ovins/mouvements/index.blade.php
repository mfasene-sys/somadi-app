@php use App\Support\Format; use App\Support\Montant; $sorties = collect($sorties)->keyBy(fn ($x) => mb_strtolower(trim($x->type_sortie))); @endphp
<x-layouts.app titre="Transferts et sorties" :sous-titre="'Mouvements du troupeau en '.$annee">
    @if ($nonLiees && auth()->user()->can('ACC.droits'))
        <a href="{{ route('ovins.rapprochements.index') }}" class="mb-4 flex items-center gap-3 rounded-lg border border-paille-500/40 bg-paille-50 px-4 py-3 text-sm text-paille-700 hover:bg-paille-100">
            <x-icone nom="link" class="size-5"/><span class="flex-1"><strong>{{ $nonLiees }}</strong> vente(s) ovine(s) historique(s) sans lien avec le registre des ventes.</span><x-icone nom="chevron-right" class="size-4"/></a>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-indicateur label="Ventes" :valeur="Format::nombre($sorties['vente']->n ?? 0)" :detail="isset($sorties['vente']) ? Montant::fcfa(Montant::base($sorties['vente']->total)) : null"/>
        <x-indicateur label="Mortalités" :valeur="Format::nombre($sorties['mortalite']->n ?? 0)" :ton="($sorties['mortalite']->n ?? 0) ? 'alerte' : null"/>
        <x-indicateur label="Réformes" :valeur="Format::nombre($sorties['reforme']->n ?? 0)"/>
        <x-indicateur label="Autres sorties" :valeur="Format::nombre($sorties['autre']->n ?? 0)"/>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Présents par site</h2>
            <div class="carte divide-y divide-stone-100 text-sm">
                @forelse ($parSite as $s)
                    <div class="flex gap-3 px-4 py-2.5"><span class="flex-1">{{ $s->nom_site }}</span><span class="text-stone-500">{{ (int) $s->f }} F · {{ (int) $s->m }} M</span><strong class="w-12 text-right tabular-nums">{{ $s->n }}</strong></div>
                @empty <p class="px-4 py-3 text-stone-500">Aucun animal en ferme.</p> @endforelse
            </div>
        </section>
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Présents par propriétaire</h2>
            <div class="carte divide-y divide-stone-100 text-sm">
                @forelse ($parProprio as $p)
                    <div class="flex items-center gap-3 px-4 py-2.5"><span class="size-2.5 rounded-full" style="background: {{ $p->couleur_hex ?: '#d6d3d1' }}"></span><span class="flex-1">{{ $p->nom_proprietaire ?: 'Sans propriétaire' }}</span><strong class="tabular-nums">{{ $p->n }}</strong></div>
                @empty <p class="px-4 py-3 text-stone-500">—</p> @endforelse
            </div>
        </section>
    </div>

    <form method="GET" class="carte mb-4 grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
        <x-champ name="annee" label="Année" type="number" :value="$annee" min="2000" max="2099"/>
        <x-choix name="type" label="Type" :options="$types->mapWithKeys(fn ($t) => [$t => \App\Domaine\Ovins\Mouvements::libelle($t)])->all()" :value="$f['type'] ?? null" vide="Tous"/>
        <x-choix name="site" label="Site" :options="$sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()" :value="$f['site'] ?? null" vide="Tous"/>
        <div class="flex items-end"><button class="btn-secondaire w-full">Filtrer</button></div>
    </form>
    @if ($mouvements->isEmpty())
        <x-vide icone="move">Aucun mouvement.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100 text-sm">
            @foreach ($mouvements as $m)
                <div class="flex flex-wrap gap-x-3 px-4 py-2.5">
                    <span class="w-20 tabular-nums text-stone-500">{{ Format::date($m->date_mvt) }}</span>
                    <span class="min-w-0 flex-1"><strong>{{ \App\Domaine\Ovins\Mouvements::libelle($m->type_mvt) }}</strong>
                        @if ($m->id_animal) · <a href="{{ route('ovins.animaux.show', $m->id_animal) }}" class="text-ferme-700 hover:underline">{{ $m->code_animal ?: '#'.$m->id_animal }}</a>@endif
                        {{ $m->nom_lot ? ' · '.$m->nom_lot : '' }}<span class="block text-xs text-stone-500">{{ $m->commentaire }} · {{ $m->utilisateur }}</span></span>
                    <span class="tabular-nums">{{ $m->quantite }}{{ $m->montant_total ? ' · '.Montant::fcfa(Montant::base($m->montant_total)) : '' }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $mouvements->links() }}</div>
    @endif
    <p class="mt-4 text-xs text-stone-500">Les transferts et sorties se saisissent depuis la fiche de chaque animal.</p>
</x-layouts.app>

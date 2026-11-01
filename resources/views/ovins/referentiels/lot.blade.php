@php use App\Support\Format; use App\Support\Montant; use App\Domaine\Ovins\Referentiels; use App\Domaine\Ovins\Animaux; @endphp
<x-layouts.app :titre="$lot->nom_lot" :sous-titre="Referentiels::libelleType($lot->type_lot).' · '.$lot->nom_site.($lot->race ? ' · '.$lot->race : '')">
    <x-slot:actions>
        @can('OVR.voir')<a href="{{ route('ovins.lots.calendrier', $lot->id_lot) }}" class="btn-secondaire"><x-icone nom="calendar" class="size-4"/> Calendrier{{ $cycles ? ' ('.$cycles.')' : '' }}</a>@endcan
        @can('OVA.gerer')<a href="{{ route('ovins.lots.edit', $lot->id_lot) }}" class="btn-secondaire">Modifier</a>@endcan
    </x-slot:actions>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-indicateur label="Effectif courant" :valeur="Format::nombre($lot->effectif_encours)" :detail="'initial '.Format::nombre($lot->effectif_initial)"/>
        <x-indicateur label="Fiches présentes" :valeur="Format::nombre($animaux->count())" :detail="$animaux->where('sexe', 'F')->count().' F · '.$animaux->where('sexe', 'M')->count().' M'"/>
        <x-indicateur label="Entrée" :valeur="Format::date($lot->date_entree)" :detail="$lot->provenance"/>
        <x-indicateur label="Valeur d’achat" :valeur="$lot->valeur_totale_achat ? Montant::fcfa(Montant::base($lot->valeur_totale_achat)) : '—'" :detail="$lot->valeur_unitaire_achat ? Montant::fcfa(Montant::base($lot->valeur_unitaire_achat)).' par tête' : null"/>
    </div>
    @if ((int) $lot->effectif_encours !== $animaux->count())
        <p class="mb-4 rounded-lg bg-paille-50 px-4 py-3 text-sm text-paille-700">L’effectif du lot ({{ $lot->effectif_encours }}) diffère du nombre de fiches présentes ({{ $animaux->count() }}). Ce n’est pas forcément une erreur si certains animaux n’ont pas de fiche individuelle.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Animaux présents</h2>
                @if ($animaux->isEmpty())
                    <p class="carte px-4 py-3 text-sm text-stone-500">Aucune fiche individuelle dans ce lot.</p>
                @else
                    <div class="carte divide-y divide-stone-100 text-sm">
                        @foreach ($animaux as $a)
                            <a href="{{ route('ovins.animaux.show', $a->id_animal) }}" class="flex gap-3 px-4 py-2.5 hover:bg-stone-50"><span class="w-5">{{ $a->sexe === 'F' ? '♀' : '♂' }}</span><span class="flex-1 font-medium">{{ $a->code_animal ?: '#'.$a->id_animal }} <span class="font-normal text-stone-500">{{ $a->nom_animal }}</span></span><span class="text-stone-500">{{ Animaux::age($a->date_naissance) }}</span></a>
                        @endforeach
                    </div>
                @endif
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Soins planifiés du lot</h2>
                @if ($soins->isEmpty())
                    <p class="carte px-4 py-3 text-sm text-stone-500">Aucun soin planifié.</p>
                @else
                    <div class="carte divide-y divide-stone-100 text-sm">
                        @foreach ($soins as $s)
                            <div class="flex gap-3 px-4 py-2.5 {{ $s->date_soin < today()->toDateString() ? 'text-red-700' : '' }}"><span class="w-20 tabular-nums">{{ Format::date($s->date_soin) }}</span><span class="flex-1">{{ $s->nom_soin ?: $s->produit }}{{ $s->dose ? ' · '.$s->dose : '' }}</span></div>
                        @endforeach
                    </div>
                    <a href="{{ route('ovins.soins.index', ['lot' => $lot->id_lot]) }}" class="mt-2 inline-block text-sm font-medium text-ferme-700 hover:underline">Gérer dans Soins ovins</a>
                @endif
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Mouvements</h2>
                <div class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($mouvements as $m)
                        <div class="flex gap-3 px-4 py-2.5"><span class="w-20 shrink-0 tabular-nums text-stone-500">{{ Format::date($m->date_mvt) }}</span>
                            <span class="flex-1">{{ \App\Domaine\Ovins\Mouvements::libelle($m->type_mvt) }}@if ($m->code_animal) · <a href="{{ route('ovins.animaux.show', $m->id_animal) }}" class="text-ferme-700 hover:underline">{{ $m->code_animal }}</a>@endif<span class="block text-xs text-stone-500">{{ $m->commentaire }}</span></span>
                            <span class="font-semibold tabular-nums {{ (int) $m->quantite < 0 || in_array($m->type_mvt, ['vente', 'mortalite', 'reforme', 'autre']) ? 'text-red-700' : 'text-ferme-700' }}">{{ in_array($m->type_mvt, ['vente', 'mortalite', 'reforme', 'autre']) ? '−'.abs((int) $m->quantite) : ((int) $m->quantite > 0 ? '+' : '').$m->quantite }}</span></div>
                    @empty <p class="px-4 py-3 text-stone-500">Aucun mouvement.</p> @endforelse
                </div>
            </section>
        </div>
        <aside class="space-y-4">
            @can('OVS.gerer')
                <details class="carte p-4">
                    <summary class="cursor-pointer font-semibold text-stone-900">Appliquer un modèle de soins au lot</summary>
                    <form method="POST" action="{{ route('ovins.soins.appliquer') }}" class="mt-3 space-y-3">
                        @csrf <input type="hidden" name="cible" value="lot"><input type="hidden" name="id_cible" value="{{ $lot->id_lot }}">
                        <x-choix name="id_modele" label="Modèle" :options="$modeles->mapWithKeys(fn ($m) => [$m->id_modele => $m->nom_modele])->all()" vide="Choisir…" required/>
                        <x-champ name="date_reference" label="Date de référence (J0)" type="date" :value="today()->toDateString()" required/>
                        <button class="btn-secondaire w-full">Planifier</button>
                    </form>
                </details>
            @endcan
            @can('OVA.gerer')
                <details class="carte p-4" @if ($errors->hasAny(['effectif', 'motif'])) open @endif>
                    <summary class="cursor-pointer font-semibold text-stone-900">Corriger l’effectif</summary>
                    <form method="POST" action="{{ route('ovins.lots.ajuster', $lot->id_lot) }}" class="mt-3 space-y-3">
                        @csrf
                        <x-champ name="effectif" label="Effectif constaté" type="number" min="0" :value="$lot->effectif_encours" required/>
                        <x-champ name="date" label="Date du constat" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                        <x-champ name="motif" label="Motif" required maxlength="500" placeholder="Comptage physique, erreur de saisie…"/>
                        <button class="btn-secondaire w-full">Corriger</button>
                        <p class="text-xs text-stone-500">La correction crée un mouvement « ajustement » ; les sorties réelles se saisissent sur la fiche de l’animal.</p>
                    </form>
                </details>
            @endcan
            @if ($lot->observations)<p class="carte whitespace-pre-line p-4 text-sm text-stone-600">{{ $lot->observations }}</p>@endif
        </aside>
    </div>
</x-layouts.app>

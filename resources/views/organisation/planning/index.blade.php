@php use App\Domaine\Organisation\Planning as P; use App\Support\Format; $t = today()->toDateString(); @endphp
<x-layouts.app titre="Planning des emplacements" sous-titre="Réservations et interventions : une réservation ne place aucun animal">
    @include('organisation.lieux._onglets')
    @if ($alertes)
        <section class="mb-6 space-y-2">
            @foreach ($alertes as [$ton, $p, $texte])
                <div class="flex flex-wrap items-center gap-2 rounded-lg px-4 py-2.5 text-sm {{ $ton === 'alerte' ? 'border border-red-200 bg-red-50 text-red-800' : 'bg-paille-50 text-paille-800' }}">
                    <strong>{{ $p->code }}</strong> · {{ $p->libelle }} ({{ Format::date($p->date_debut) }} → {{ Format::date($p->date_fin) }}) : {{ $texte }}</div>
            @endforeach
        </section>
    @endif
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Prévu et en cours</h2>
                @if ($periodes->isEmpty())
                    <x-vide icone="calendar">Rien de planifié.</x-vide>
                @else
                    <div class="space-y-2">
                        @foreach ($periodes as $p)
                            <div class="carte p-4 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong>{{ $p->code ?: $p->nom_batiment }}</strong><span class="text-stone-500">{{ P::NATURES[$p->nature] }} · {{ $p->libelle }}{{ $p->quantite ? ' · '.$p->quantite.' places' : '' }}</span>
                                    <x-statut :ton="P::STATUTS[$p->statut][1]" class="ml-auto">{{ P::STATUTS[$p->statut][0] }}</x-statut>
                                </div>
                                <p class="mt-1 tabular-nums text-stone-600">{{ Format::dateLongue($p->date_debut) }} → {{ Format::dateLongue($p->date_fin) }}</p>
                                @can('IFP.gerer')
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @if ($p->statut === 'prevu' && $p->nature !== 'reservation' && $t >= $p->date_debut && $t <= $p->date_fin)
                                            <form method="POST" action="{{ route('organisation.planning.transition', [$p->id, 'demarrer']) }}">@csrf<button class="btn-primaire min-h-9 text-xs">Démarrer</button></form>
                                        @endif
                                        @if ($p->statut === 'en_cours')
                                            <form method="POST" action="{{ route('organisation.planning.transition', [$p->id, 'terminer']) }}">@csrf<button class="btn-primaire min-h-9 text-xs">Terminer et remettre en service</button></form>
                                            @if (auth()->user()->estAdmin())
                                                <details><summary class="cursor-pointer text-xs text-stone-500">Clôture administrative</summary>
                                                    <form method="POST" action="{{ route('organisation.planning.transition', [$p->id, 'administrative']) }}" class="mt-2 flex gap-2">@csrf<input name="motif" required minlength="10" placeholder="Motif" class="champ min-h-9 text-xs"><button class="btn-secondaire min-h-9 text-xs">OK</button></form></details>
                                            @endif
                                        @endif
                                        @if ($p->statut === 'prevu' && $p->nature === 'reservation' && $t >= $p->date_debut)
                                            <form method="POST" action="{{ route('organisation.planning.transition', [$p->id, 'cloturer']) }}">@csrf<button class="btn-secondaire min-h-9 text-xs">Clôturer la réservation</button></form>
                                        @endif
                                        @if ($p->statut === 'prevu')
                                            <details><summary class="cursor-pointer text-xs text-stone-500">Annuler</summary>
                                                <form method="POST" action="{{ route('organisation.planning.transition', [$p->id, 'annuler']) }}" class="mt-2 flex gap-2">@csrf<input name="motif" required minlength="3" placeholder="Motif" class="champ min-h-9 text-xs"><button class="btn-secondaire min-h-9 text-xs">OK</button></form></details>
                                        @endif
                                    </div>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            @if ($historique->isNotEmpty())
                <section>
                    <h2 class="mb-3 font-semibold text-stone-900">Historique</h2>
                    <div class="carte divide-y divide-stone-100 text-sm">
                        @foreach ($historique as $p)
                            <div class="flex flex-wrap gap-3 px-4 py-2"><span class="tabular-nums text-stone-500">{{ Format::date($p->date_debut) }} → {{ Format::date($p->date_fin) }}</span><span class="flex-1">{{ $p->code }} · {{ P::NATURES[$p->nature] }} · {{ $p->libelle }}{{ $p->cloture_mode === 'administrative' ? ' (clôture administrative)' : '' }}</span><x-statut :ton="P::STATUTS[$p->statut][1]">{{ P::STATUTS[$p->statut][0] }}</x-statut></div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
        @can('IFP.gerer')
            <form method="POST" action="{{ route('organisation.planning.store') }}" class="carte h-fit space-y-3 p-4">
                @csrf <x-cle/>
                <h2 class="font-semibold text-stone-900">Planifier</h2>
                <x-choix name="id_batiment" label="Emplacement" :options="$lieux->mapWithKeys(fn ($l) => [$l->id => $l->code.' · '.$l->nom_batiment.' ('.$l->capacite.')'])->all()" vide="Choisir…" required/>
                <x-choix name="nature" label="Nature" :options="P::NATURES" onchange="document.getElementById('bloc-qte').hidden = this.value !== 'reservation'"/>
                <div class="grid grid-cols-2 gap-3">
                    <x-champ name="date_debut" label="Du" type="date" :value="$t" :min="$t" required/>
                    <x-champ name="date_fin" label="Au (inclus)" type="date" :value="today()->addDays(6)->toDateString()" required/>
                </div>
                <div id="bloc-qte" @if (old('nature', 'reservation') !== 'reservation') hidden @endif><x-champ name="quantite" label="Places réservées" type="number" min="1"/></div>
                <x-champ name="libelle" label="Libellé" maxlength="150" placeholder="Arrivée lot de janvier…"/>
                <x-champ name="commentaire" label="Commentaire" type="textarea"/>
                <button class="btn-primaire w-full">Planifier</button>
                <p class="text-xs text-stone-500">Plusieurs réservations peuvent se chevaucher tant que le total simultané ne dépasse pas la capacité. Une intervention ne chevauche rien.</p>
            </form>
        @endcan
    </div>
</x-layouts.app>

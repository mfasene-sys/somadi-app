@php use App\Domaine\Organisation\Recurrence as R; use App\Support\Format; $peut = \App\Domaine\Organisation\Activites::planificateur(); $e = $edition; @endphp
<x-layouts.app :titre="$r->titre" sous-titre="Routine : tâches récurrentes et génération">
    @include('organisation.activites._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        <div class="min-w-0 space-y-6">
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Tâches de la routine</h2>
                <div class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($taches as $t)
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $t->actif ? '' : 'opacity-60' }}">
                            <span class="flex-1"><strong>{{ $t->nom }}</strong>
                                <span class="block text-xs text-stone-500">{{ R::TYPES[$t->type_recurrence] }}
                                    @if ($t->type_recurrence === 'hebdo') · {{ $t->jours_semaine }}{{ $t->intervalle_semaines > 1 ? ' toutes les '.$t->intervalle_semaines.' semaines' : '' }}@endif
                                    @if (in_array($t->type_recurrence, ['mensuel', 'annuel'])) · jours {{ $t->jours_mois ?: $t->jour_mois }}{{ $t->mois_annee ? ' · mois '.$t->mois_annee : '' }}@endif
                                    @if ($t->type_recurrence === 'intervalle') · toutes les {{ $t->intervalle_min }} min de {{ substr($t->horaire_debut, 0, 5) }} à {{ substr($t->horaire_fin, 0, 5) }}@else · {{ substr($t->horaire_debut ?: '08:00', 0, 5) }}@endif
                                    · {{ $t->duree_estimee_min }} min</span></span>
                            @if ($peut)<a href="?tache={{ $t->id }}#tache" class="text-xs font-semibold text-ferme-700">Modifier</a>@endif
                        </div>
                    @empty <p class="px-4 py-3 text-stone-500">Aucune tâche : ajoutez-en une.</p> @endforelse
                </div>
            </section>
            <section class="carte p-4">
                <h2 class="mb-3 font-semibold text-stone-900">Générer les tâches</h2>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <x-champ name="du" label="Du" type="date" :value="request('du', today()->toDateString())" required/>
                    <x-champ name="au" label="Au" type="date" :value="request('au', today()->addDays(13)->toDateString())" required/>
                    <button class="btn-secondaire">Voir l’aperçu</button>
                </form>
                <p class="mt-2 text-xs text-stone-500">366 jours au plus. Les jours 29 à 31 inexistants sont rabattus sur la fin du mois (février : 30 → 27, 31 → 28).</p>
                @if ($apercu)
                    <p class="mt-4 text-sm"><strong>{{ $apercu['nouvelles'] }}</strong> tâche(s) à créer sur {{ count($apercu['lignes']) }} occurrence(s) ; les autres existent déjà.</p>
                    <div class="mt-2 max-h-72 overflow-y-auto rounded-lg border border-stone-200 text-xs">
                        @foreach ($apercu['lignes'] as $l)
                            <div class="flex gap-3 border-b border-stone-100 px-3 py-1.5 {{ $l['existe'] ? 'text-stone-400 line-through' : '' }}"><span class="w-32 tabular-nums">{{ Format::date(substr($l['debut'], 0, 10)) }} {{ substr($l['debut'], 11, 5) }}</span><span>{{ $l['nom'] }}</span></div>
                        @endforeach
                    </div>
                    @if ($peut && $apercu['nouvelles'])
                        <form method="POST" action="{{ route('activites.routines.generer', $r->id) }}" class="mt-3 flex flex-wrap items-center gap-3">@csrf <x-cle/>
                            <input type="hidden" name="du" value="{{ $apercu['du'] }}"><input type="hidden" name="au" value="{{ $apercu['au'] }}"><input type="hidden" name="empreinte" value="{{ $apercu['empreinte'] }}">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" required class="size-5"> Je confirme la création</label>
                            <button class="btn-primaire">Créer {{ $apercu['nouvelles'] }} tâche(s)</button></form>
                    @endif
                @endif
            </section>
        </div>
        @if ($peut)
            <aside class="space-y-4">
                <form id="tache" method="POST" action="{{ route('activites.routines.taches', [$r->id, $e?->id]) }}" class="carte space-y-3 p-4">@csrf
                    <h2 class="font-semibold text-stone-900">{{ $e ? 'Modifier la tâche' : 'Ajouter une tâche' }}</h2>
                    <x-champ name="nom" label="Nom" :value="$e?->nom" required maxlength="200"/>
                    <x-choix name="type_recurrence" label="Récurrence" :options="R::TYPES" :value="$e?->type_recurrence ?? 'quotidien'"/>
                    <fieldset><legend class="etiquette">Jours de la semaine (hebdo)</legend><div class="flex flex-wrap gap-1">
                        @foreach (R::JOURS as $k => $lib)<label class="cursor-pointer"><input type="checkbox" name="jours_semaine[]" value="{{ $k }}" class="peer sr-only" @checked(in_array($k, explode(',', (string) $e?->jours_semaine)))><span class="block rounded-full border border-stone-300 px-2.5 py-1 text-xs peer-checked:border-ferme-600 peer-checked:bg-ferme-50">{{ $lib }}</span></label>@endforeach</div></fieldset>
                    <div class="grid grid-cols-2 gap-3">
                        <x-champ name="intervalle_semaines" label="Toutes les (semaines)" type="number" min="1" :value="$e?->intervalle_semaines ?? 1"/>
                        <x-champ name="jours_mois" label="Jours du mois" :value="$e?->jours_mois" placeholder="1, 15, 31"/>
                        <x-champ name="mois_annee" label="Mois (annuel)" :value="$e?->mois_annee" placeholder="3, 9"/>
                        <x-champ name="intervalle_min" label="Intervalle (min)" type="number" min="1" :value="$e?->intervalle_min"/>
                        <x-champ name="horaire_debut" label="Heure" type="time" :value="$e?->horaire_debut ? substr($e->horaire_debut, 0, 5) : '08:00'"/>
                        <x-champ name="horaire_fin" label="Fin de plage" type="time" :value="$e?->horaire_fin ? substr($e->horaire_fin, 0, 5) : null"/>
                        <x-champ name="duree_estimee_min" label="Durée (min)" type="number" min="1" :value="$e?->duree_estimee_min ?? 60"/>
                        <x-champ name="delai_echeance_min" label="Échéance après (min)" type="number" min="0" :value="$e?->delai_echeance_min"/>
                    </div>
                    <x-choix name="id_categorie" label="Catégorie" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->libelle])->all()" :value="$e?->id_categorie" vide="Aucune"/>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="actif" value="1" class="size-5" @checked($e?->actif ?? true)> Active</label>
                    <button class="btn-primaire w-full">Enregistrer</button>
                </form>
                <details class="carte p-4"><summary class="cursor-pointer font-semibold text-stone-900">Paramètres de la routine</summary>
                    <form method="POST" action="{{ route('activites.routines.enregistrer', $r->id) }}" class="mt-3 space-y-3">@csrf
                        @include('organisation.activites._routine', ['r' => $r])
                        <button class="btn-secondaire w-full">Enregistrer</button></form></details>
            </aside>
        @endif
    </div>
</x-layouts.app>

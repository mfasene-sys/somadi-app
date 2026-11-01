@php
    use App\Domaine\Organisation\Activites as A; use App\Support\Format;
    $actif = in_array($t->statut, ['planifie', 'en_cours', 'reporte'], true);
    $exec = $role && $role !== 'observateur';
    $u = auth()->user();
    $peutTerminer = $actif && ($gestionnaire || ($exec && ($u->can('ACT.cloturer') || $u->roleCanonique() === 'technicien')));
    $d = fn ($v) => $v ? Format::date(substr($v, 0, 10)).' '.substr($v, 11, 5) : '—';
@endphp
<x-layouts.app :titre="$t->nom" :sous-titre="($t->categorie ?: 'Sans catégorie').($t->projet ? ' · '.$t->projet : '').($t->routine ? ' · routine '.$t->routine : '')">
    <x-slot:actions>
        <x-statut :ton="A::STATUTS[$t->statut][1]" class="px-3 py-1 text-sm">{{ A::STATUTS[$t->statut][0] }}</x-statut>
        @if ($planificateur && $actif)<a href="{{ route('activites.taches.edit', $t->id) }}" class="btn-secondaire">Modifier</a>@endif
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="carte grid grid-cols-[8rem_1fr] gap-y-1.5 p-4 text-sm">
                <span class="text-stone-500">Priorité</span><span><x-statut :ton="A::PRIORITES[$t->priorite][1]">{{ A::PRIORITES[$t->priorite][0] }}</x-statut></span>
                <span class="text-stone-500">Prévu</span><span>{{ $d($t->date_debut_prevue) }} → {{ $d($t->date_fin_prevue) }}</span>
                <span class="text-stone-500">Échéance</span><span class="{{ $actif && $t->date_echeance && $t->date_echeance < now()->format('Y-m-d H:i:s') ? 'font-semibold text-red-700' : '' }}">{{ $d($t->date_echeance) }}</span>
                <span class="text-stone-500">Durée</span><span>{{ $t->duree_estimee_min ? $t->duree_estimee_min.' min estimées' : '—' }}{{ $t->duree_reelle_min !== null ? ' · '.$t->duree_reelle_min.' min réelles' : '' }}</span>
                @if ($t->date_realisation)<span class="text-stone-500">Réalisée le</span><span>{{ $d($t->date_realisation) }}</span>@endif
                @if ($t->description)<p class="col-span-2 mt-2 whitespace-pre-line text-stone-700">{{ $t->description }}</p>@endif
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Affectations</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($aff as $a)
                        <span class="carte px-3 py-1.5 text-sm">{{ $a->type_cible === 'equipe' ? 'Équipe '.$a->nom_equipe : $a->nom_user }} <span class="text-xs text-stone-500">· {{ A::ROLES[$a->role_affectation] }}</span></span>
                    @endforeach
                </div>
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Historique</h2>
                <ol class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($pointages as $p)
                        <li class="px-4 py-2.5"><span class="text-stone-500">{{ $d($p->ts_action) }} · {{ $p->auteur }}</span> · <strong>{{ ['demarrer' => 'Démarrée', 'terminer' => 'Terminée', 'commenter' => 'Commentaire', 'pause' => 'Pause', 'reprendre' => 'Reprise'][$p->action] }}</strong>{{ $p->duree_delta_min ? ' ('.$p->duree_delta_min.' min)' : '' }}
                            @if ($p->commentaire)<span class="block whitespace-pre-line text-stone-700">{{ $p->commentaire }}</span>@endif</li>
                    @empty <li class="px-4 py-3 text-stone-500">Aucune action.</li> @endforelse
                </ol>
            </section>
        </div>
        <aside class="space-y-3">
            @if ($actif && in_array($t->statut, ['planifie', 'reporte'], true) && ($gestionnaire || $exec))
                <form method="POST" action="{{ route('activites.taches.agir', [$t->id, 'demarrer']) }}">@csrf <x-cle/><input type="hidden" name="empreinte" value="{{ $empreinte }}"><button class="btn-primaire w-full">Démarrer</button></form>
            @endif
            @if ($peutTerminer)
                <form method="POST" action="{{ route('activites.taches.agir', [$t->id, 'terminer']) }}" class="carte space-y-2 p-4">@csrf <x-cle/><input type="hidden" name="empreinte" value="{{ $empreinte }}">
                    <x-champ name="duree_reelle_min" label="Durée réelle (facultatif)" type="number" min="0" suffixe="min"/>
                    <x-champ name="motif" label="Commentaire" id="motif_fin"/>
                    <button class="btn-primaire w-full">Terminer</button></form>
            @endif
            @if ($actif && $planificateur)
                <details class="carte p-4"><summary class="cursor-pointer font-semibold text-stone-900">Reporter</summary>
                    <form method="POST" action="{{ route('activites.taches.agir', [$t->id, 'reporter']) }}" class="mt-3 space-y-2">@csrf <x-cle/><input type="hidden" name="empreinte" value="{{ $empreinte }}">
                        <x-champ name="debut" label="Nouveau début" type="datetime-local" required/>
                        <x-champ name="duree_min" label="Durée" type="number" min="1" max="1440" suffixe="min" :value="$t->duree_estimee_min ?: 60" required/>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="deplacer_echeance" value="1" class="size-4"> Déplacer aussi l’échéance</label>
                        <x-champ name="motif" label="Motif" required id="motif_report"/>
                        <button class="btn-secondaire w-full">Reporter</button></form></details>
            @endif
            @if ($actif && $gestionnaire)
                <details class="carte p-4"><summary class="cursor-pointer font-semibold text-red-700">Annuler la tâche</summary>
                    <form method="POST" action="{{ route('activites.taches.agir', [$t->id, 'annuler']) }}" class="mt-3 space-y-2">@csrf <x-cle/><input type="hidden" name="empreinte" value="{{ $empreinte }}">
                        <x-champ name="motif" label="Motif" required id="motif_annul"/><button class="btn-secondaire w-full">Annuler la tâche</button></form></details>
            @endif
            @if ($role || $planificateur)
                <form method="POST" action="{{ route('activites.taches.agir', [$t->id, 'commenter']) }}" class="carte space-y-2 p-4">@csrf <x-cle/>
                    <x-champ name="motif" label="Commentaire" type="textarea" required id="motif_com"/><button class="btn-secondaire w-full">Commenter</button></form>
            @endif
        </aside>
    </div>
</x-layouts.app>

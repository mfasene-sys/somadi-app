@php use App\Domaine\Organisation\Rh; use App\Support\Format; use App\Support\Montant; $d = $s['droits']; @endphp
<x-layouts.app :titre="trim($e->prenom.' '.$e->nom)" :sous-titre="($e->poste ?: 'Poste non précisé').' · '.($e->matricule ?: 'sans matricule').' · '.Montant::fcfa(Montant::base($e->salaire_mensuel)).' par mois'">
    <x-slot:actions>
        <a href="?annee={{ $annee - 1 }}" class="btn-secondaire">‹ {{ $annee - 1 }}</a><span class="self-center px-2 font-bold tabular-nums">{{ $annee }}</span><a href="?annee={{ $annee + 1 }}" class="btn-secondaire">{{ $annee + 1 }} ›</a>
        @can('RH.gerer')<a href="{{ route('rh.employes.edit', $e->id) }}" class="btn-secondaire">Modifier</a>@endcan
    </x-slot:actions>
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-indicateur label="Droits" :valeur="$s['quota'].' j'" :detail="$d->quota_libre.' + bonus '.$d->bonus"/>
        <x-indicateur label="Pris" :valeur="$s['pris'].' j'"/>
        <x-indicateur label="Reste" :valeur="$s['reste'].' j'" :ton="$s['reste'] < 0 ? 'alerte' : null"/>
        <x-indicateur label="Taux journalier" :valeur="Montant::fcfa($taux)" :detail="$s['a_retenir'] ? $s['a_retenir'].' j à retenir' : 'salaire ÷ 30'"/>
    </div>
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Absences</h2>
                <div class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($absences as $a)
                        <div class="flex flex-wrap gap-3 px-4 py-2.5"><span class="tabular-nums text-stone-500">{{ Format::date($a->date_debut) }} → {{ Format::date($a->date_fin) }}</span><span class="flex-1">{{ Rh::TYPES[$a->type] }}{{ $a->jours_imputes ? ' · '.$a->jours_imputes.' j imputés' : '' }}{{ $a->motif ? ' · '.$a->motif : '' }}</span><x-statut :ton="Rh::STATUTS[$a->statut][1]">{{ Rh::STATUTS[$a->statut][0] }}</x-statut></div>
                    @empty <p class="px-4 py-3 text-stone-500">Aucune absence.</p> @endforelse
                </div>
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Mouvements de congés {{ $annee }}</h2>
                <div class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($mouvements as $m)
                        <div class="flex flex-wrap gap-3 px-4 py-2.5"><span class="w-20 tabular-nums text-stone-500">{{ Format::date($m->date_mvt) }}</span><span class="flex-1">{{ Rh::MOUVEMENTS[$m->type_mvt] }}{{ $m->reference_table === 'correction' ? ' (correction)' : '' }}{{ $m->mois_application ? ' · appliqué en '.\Illuminate\Support\Carbon::parse($m->mois_application)->locale('fr')->isoFormat('MMMM YYYY') : '' }}</span>
                            <span class="tabular-nums">{{ $m->jours }} j{{ $m->montant !== null ? ' · '.Montant::fcfa(Montant::base($m->montant)) : '' }}</span></div>
                    @empty <p class="px-4 py-3 text-stone-500">Aucun mouvement.</p> @endforelse
                </div>
            </section>
        </div>
        <aside class="space-y-4">
            @can('RH.gerer')
                <form method="POST" action="{{ route('rh.employes.droits', $e->id) }}" class="carte space-y-3 p-4">@csrf <input type="hidden" name="annee" value="{{ $annee }}">
                    <h2 class="font-semibold text-stone-900">Droits {{ $annee }}</h2>
                    <div class="grid grid-cols-3 gap-2"><x-champ name="quota_libre" label="Libres" type="number" :value="$d->quota_libre"/><x-champ name="bonus" label="Bonus" type="number" :value="$d->bonus"/><x-champ name="quota_impose" label="Imposés" type="number" :value="$d->quota_impose"/></div>
                    <x-champ name="note_bonus" label="Note" :value="$d->note_bonus" maxlength="255"/>
                    <button class="btn-secondaire w-full">Enregistrer</button>
                    <p class="text-xs text-stone-500">Les jours imposés (Korité, Tabaski…) ne sont pas décomptés et ne s’ajoutent pas au quota.</p>
                </form>
            @endcan
            @can('RH.cloturer')
                <details class="carte p-4"><summary class="cursor-pointer font-semibold text-stone-900">Écriture de correction</summary>
                    <form method="POST" action="{{ route('rh.employes.corriger', $e->id) }}" class="mt-3 space-y-3">@csrf <input type="hidden" name="annee" value="{{ $annee }}">
                        <x-choix name="type" label="Type" :options="['PRISE' => 'Jours pris', 'PAIEMENT' => 'Paiement de jours', 'RETENUE' => 'Retenue']"/>
                        <x-champ name="jours" label="Jours (+ ou −)" type="number" required/>
                        <x-champ name="motif" label="Motif" type="textarea" required minlength="10"/>
                        <button class="btn-secondaire w-full">Enregistrer la correction</button>
                        <p class="text-xs text-stone-500">Rien n’est effacé : la correction s’ajoute aux mouvements, avec son motif dans le journal.</p></form></details>
            @endcan
        </aside>
    </div>
</x-layouts.app>

@php use App\Domaine\Organisation\Rh; use App\Support\Format; @endphp
<x-layouts.app titre="Absences et congés">
    @include('organisation.rh._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0">
            <form method="GET" class="mb-3 flex flex-wrap gap-2">
                <select name="statut" class="champ max-w-44" onchange="this.form.submit()"><option value="">Tous les états</option>@foreach (Rh::STATUTS as $k => [$l])<option value="{{ $k }}" @selected(($f['statut'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
                <select name="employe" class="champ max-w-56" onchange="this.form.submit()"><option value="">Tout le personnel</option>@foreach ($employes as $e)<option value="{{ $e->id }}" @selected(($f['employe'] ?? '') == $e->id)>{{ trim($e->prenom.' '.$e->nom) }}</option>@endforeach</select>
            </form>
            @if ($absences->isEmpty())<x-vide icone="calendar">Aucune demande.</x-vide>
            @else
                <div class="carte divide-y divide-stone-100 text-sm">
                    @foreach ($absences as $a)
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <span class="min-w-0 flex-1"><a href="{{ route('rh.employes.show', $a->employe_id) }}" class="font-medium text-stone-900 hover:underline">{{ trim($a->prenom.' '.$a->nom) }}</a> · {{ Rh::TYPES[$a->type] }}
                                <span class="block text-xs text-stone-500">{{ Format::date($a->date_debut) }} → {{ Format::date($a->date_fin) }}{{ $a->type === 'conge' ? ' · '.($a->statut === 'validee' ? $a->jours_imputes : Rh::joursImputes($a->date_debut, $a->date_fin)).' j décomptés' : '' }}{{ $a->motif ? ' · '.$a->motif : '' }}</span></span>
                            <x-statut :ton="Rh::STATUTS[$a->statut][1]">{{ Rh::STATUTS[$a->statut][0] }}</x-statut>
                            @if ($a->statut === 'soumise')
                                @can('RH.gerer')
                                    <form method="POST" action="{{ route('rh.absences.traiter', $a->id) }}" class="flex gap-1">@csrf
                                        <button name="decision" value="validee" class="btn-primaire min-h-9 px-3 text-xs">Valider</button>
                                        <button name="decision" value="refusee" class="btn-secondaire min-h-9 px-3 text-xs">Refuser</button>
                                        <button name="decision" value="annulee" class="btn-secondaire min-h-9 px-3 text-xs">Annuler</button></form>
                                @endcan
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-3">{{ $absences->links() }}</div>
            @endif
        </div>
        @can('RH.gerer')
            <form method="POST" action="{{ route('rh.absences.store') }}" class="carte h-fit space-y-3 p-4">@csrf <x-cle/>
                <h2 class="font-semibold text-stone-900">Nouvelle demande</h2>
                <x-choix name="employe_id" label="Employé" :options="$employes->mapWithKeys(fn ($e) => [$e->id => trim($e->prenom.' '.$e->nom)])->all()" vide="Choisir…" required/>
                <x-choix name="type" label="Type" :options="Rh::TYPES"/>
                <div class="grid grid-cols-2 gap-3"><x-champ name="date_debut" label="Du" type="date" required/><x-champ name="date_fin" label="Au (inclus)" type="date" required/></div>
                <x-champ name="motif" label="Motif" maxlength="255"/>
                <button class="btn-primaire w-full">Enregistrer</button>
                <p class="text-xs text-stone-500">Un congé décompte les jours du début à la fin, hors dimanches et jours imposés. Une absence à cheval sur deux années se saisit en deux demandes.</p>
            </form>
        @endcan
    </div>
</x-layouts.app>

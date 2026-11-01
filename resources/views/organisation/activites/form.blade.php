@php
    use App\Domaine\Organisation\Activites as A;
    $dt = fn ($v) => $v ? str_replace(' ', 'T', substr($v, 0, 16)) : null;
    $resp = $aff->firstWhere('role_affectation', 'responsable')?->id_user;
    $exec = $aff->where('type_cible', 'user')->where('role_affectation', 'executeur')->pluck('id_user')->all();
    $obs = $aff->where('type_cible', 'user')->where('role_affectation', 'observateur')->pluck('id_user')->all();
    $eqs = $aff->where('type_cible', 'equipe')->pluck('id_equipe')->all();
    $users = $utilisateurs->mapWithKeys(fn ($u) => [$u->id => trim($u->prenom.' '.$u->nom).' ('.\App\Support\Roles::libelle($u->role).')'])->all();
@endphp
<x-layouts.app :titre="$t ? 'Modifier la tâche' : 'Nouvelle tâche'">
    <form method="POST" action="{{ $t ? route('activites.taches.update', $t->id) : route('activites.taches.store') }}" class="carte max-w-3xl space-y-5 p-5 sm:p-6">
        @csrf <x-cle/> @if ($t) @method('PUT') <input type="hidden" name="empreinte" value="{{ $empreinte }}"> @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-champ name="nom" label="Titre" :value="$t?->nom" required maxlength="200" class="sm:col-span-2"/>
            <x-champ name="description" label="Description" type="textarea" :value="$t?->description" class="sm:col-span-2"/>
            <x-choix name="id_categorie" label="Catégorie" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->libelle])->all()" :value="$t?->id_categorie" vide="Aucune"/>
            <x-choix name="priorite" label="Priorité" :options="collect(A::PRIORITES)->map(fn ($p) => $p[0])->all()" :value="$t?->priorite ?? 'normale'"/>
            @if (! $t || $t->source_type === 'projet')
                <x-choix name="id_projet" label="Projet" :options="$projets->mapWithKeys(fn ($p) => [$p->id => $p->titre])->all()" :value="$t?->id_projet" vide="Aucun" class="sm:col-span-2"/>
            @endif
            <x-champ name="date_debut_prevue" label="Début prévu" type="datetime-local" :value="$dt($t?->date_debut_prevue)"/>
            <x-champ name="date_fin_prevue" label="Fin prévue" type="datetime-local" :value="$dt($t?->date_fin_prevue)"/>
            <x-champ name="date_echeance" label="Échéance" type="datetime-local" :value="$dt($t?->date_echeance)"/>
            <x-champ name="duree_estimee_min" label="Durée estimée" type="number" min="1" suffixe="min" :value="$t?->duree_estimee_min"/>
        </div>
        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 font-semibold text-stone-900">Affectations <span class="text-xs font-normal text-stone-500">(au moins une)</span></legend>
            <x-choix name="responsable" label="Responsable" :options="$users" :value="$resp" vide="Aucun"/>
            <div><label class="etiquette" for="equipes">Équipes</label><select id="equipes" name="equipes[]" multiple class="champ h-28 py-1">@foreach ($equipes as $e)<option value="{{ $e->id }}" @selected(in_array($e->id, old('equipes', $eqs)))>{{ $e->nom }}</option>@endforeach</select></div>
            <div><label class="etiquette" for="executeurs">Exécutants</label><select id="executeurs" name="executeurs[]" multiple class="champ h-32 py-1">@foreach ($users as $id => $n)<option value="{{ $id }}" @selected(in_array($id, old('executeurs', $exec)))>{{ $n }}</option>@endforeach</select></div>
            <div><label class="etiquette" for="observateurs">Observateurs</label><select id="observateurs" name="observateurs[]" multiple class="champ h-32 py-1">@foreach ($users as $id => $n)<option value="{{ $id }}" @selected(in_array($id, old('observateurs', $obs)))>{{ $n }}</option>@endforeach</select></div>
            <p class="text-xs text-stone-500 sm:col-span-2">Les membres d’une équipe affectée voient et exécutent la tâche ; un observateur la voit sans pouvoir agir.</p>
        </fieldset>
        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ $t ? route('activites.taches.show', $t->id) : route('activites.index') }}" class="btn-secondaire">Annuler</a>
            <button class="btn-primaire">Enregistrer</button>
        </div>
    </form>
</x-layouts.app>

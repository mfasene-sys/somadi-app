@php use App\Support\Montant; @endphp
<x-layouts.app :titre="$e ? 'Modifier '.trim($e->prenom.' '.$e->nom) : 'Nouvel employé'">
    <form method="POST" action="{{ $e ? route('rh.employes.update', $e->id) : route('rh.employes.store') }}" class="carte grid max-w-2xl gap-4 p-5 sm:grid-cols-2 sm:p-6">
        @csrf @if ($e) @method('PUT') @else <x-cle/> @endif
        <x-champ name="prenom" label="Prénom" :value="$e?->prenom" maxlength="80"/>
        <x-champ name="nom" label="Nom" :value="$e?->nom" required maxlength="80"/>
        <x-champ name="matricule" label="Matricule" :value="$e?->matricule" maxlength="30"/>
        <x-champ name="telephone" label="Téléphone" type="tel" :value="$e?->telephone" maxlength="30"/>
        <x-champ name="poste" label="Poste" :value="$e?->poste" maxlength="80"/>
        <x-choix name="site_id" label="Site" :options="$sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()" :value="$e?->site_id" vide="Aucun"/>
        <x-champ name="date_entree" label="Date d’entrée" type="date" :value="$e?->date_entree"/>
        <x-champ name="salaire_mensuel" label="Salaire mensuel" suffixe="FCFA" inputmode="decimal" :value="$e ? Montant::champ(Montant::base($e->salaire_mensuel)) : null" required/>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="actif" value="1" class="size-5" @checked($e?->actif ?? true)> Actif</label>
        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:col-span-2 sm:flex-row sm:justify-end">
            <a href="{{ $e ? route('rh.employes.show', $e->id) : route('rh.employes.index') }}" class="btn-secondaire">Annuler</a><button class="btn-primaire">Enregistrer</button></div>
    </form>
</x-layouts.app>

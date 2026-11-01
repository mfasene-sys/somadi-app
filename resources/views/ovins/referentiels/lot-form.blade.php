@php use App\Support\Empreinte; use App\Support\Montant; $m = fn ($v) => $v !== null ? Montant::champ(Montant::base($v)) : null; @endphp
<x-layouts.app :titre="$lot ? 'Modifier '.$lot->nom_lot : 'Nouveau lot ovin'">
    <form method="POST" action="{{ $lot ? route('ovins.lots.update', $lot->id_lot) : route('ovins.lots.store') }}" class="carte max-w-2xl space-y-5 p-5 sm:p-6">
        @csrf
        @if ($lot) @method('PUT') <input type="hidden" name="empreinte" value="{{ Empreinte::de($lot) }}"> @else <x-cle/> @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-champ name="nom_lot" label="Nom du lot" :value="$lot?->nom_lot" required maxlength="150" class="sm:col-span-2"/>
            <x-choix name="type_lot" label="Type" :options="config('somadi.ovins.types_lot')" :value="$lot?->type_lot" vide="Choisir…" required/>
            <x-champ name="race" label="Race" :value="$lot?->race" maxlength="100"/>
            @if ($lot)
                <div><span class="etiquette">Site</span><p class="champ flex items-center bg-stone-50">{{ $sites->firstWhere('id_site', $lot->id_site)?->nom_site }}</p></div>
                <x-choix name="statut" label="Statut" :options="['actif' => 'Actif', 'clos' => 'Clos']" :value="$lot->statut"/>
            @else
                <x-choix name="id_site" label="Site" :options="$sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()" vide="Choisir…" required/>
                <x-champ name="effectif_initial" label="Effectif à la constitution" type="number" min="0" value="0" required aide="Animaux comptés dans le lot à sa création (fiches individuelles ou non)."/>
            @endif
            <x-champ name="date_entree" label="Date d’entrée" type="date" :value="$lot?->date_entree ?? today()->toDateString()" :max="today()->toDateString()" required/>
            <x-champ name="provenance" label="Provenance" :value="$lot?->provenance" maxlength="150"/>
            <x-champ name="valeur_unitaire_achat" label="Valeur d’achat par tête" suffixe="FCFA" inputmode="decimal" :value="$m($lot?->valeur_unitaire_achat)"/>
            <x-champ name="valeur_totale_achat" label="Valeur d’achat totale" suffixe="FCFA" inputmode="decimal" :value="$m($lot?->valeur_totale_achat)"/>
        </div>
        <x-champ name="observations" label="Observations" type="textarea" :value="$lot?->observations"/>
        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ $lot ? route('ovins.lots.show', $lot->id_lot) : route('ovins.lots.index') }}" class="btn-secondaire">Annuler</a>
            <button class="btn-primaire">Enregistrer</button>
        </div>
    </form>
</x-layouts.app>

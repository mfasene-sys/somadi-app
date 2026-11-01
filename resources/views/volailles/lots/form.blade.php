<x-layouts.app :titre="$lot ? 'Modifier '.$lot->nom_lot : 'Nouveau lot'">
    <form method="POST" action="{{ $lot ? route('lots.update', $lot->id) : route('lots.store') }}" class="carte max-w-3xl space-y-5 p-5 sm:p-6">
        @csrf
        @if ($lot)
            @method('PUT')
            <input type="hidden" name="empreinte" value="{{ $empreinte }}">
            <p class="rounded-lg bg-paille-50 px-3 py-2 text-sm text-paille-700">Modifier le nombre initial ne change pas l’effectif courant ({{ $lot->effectif_en_cours }}) : seuls les mouvements le font évoluer.</p>
        @else
            <x-cle/>
        @endif

        <datalist id="especes">@foreach ($especes as $e)<option value="{{ $e }}">@endforeach</datalist>
        <datalist id="races">@foreach ($races as $r)<option value="{{ $r->race }}">{{ $r->espece }}</option>@endforeach</datalist>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-champ name="nom_lot" label="Nom du lot" :value="$lot->nom_lot ?? null" required maxlength="100" class="sm:col-span-2"/>
            <x-champ name="espece" label="Espèce" :value="$lot->espece ?? 'poulet'" required maxlength="50" list="especes"/>
            <x-champ name="race" label="Race" :value="$lot->race ?? null" required maxlength="50" list="races"/>
            <x-champ name="nombre_initial" label="Nombre initial de sujets" type="number" min="1" :value="$lot->nombre_initial ?? null" required inputmode="numeric"/>
            <x-champ name="date_entree" label="Date d’entrée" type="date" :value="$lot->date_entree ?? today()->toDateString()" required/>
            <x-champ name="origine" label="Origine" :value="$lot->origine ?? null" maxlength="100" aide="Fournisseur, incubation…"/>
            <x-choix name="id_plan" label="Plan d’alimentation" :options="$plans->mapWithKeys(fn ($p) => [$p->id => $p->nom_plan.' ('.$p->espece.')'])->all()" :value="$lot->id_plan ?? null" vide="Aucun"/>
            <x-champ name="remarques" label="Remarques" type="textarea" :value="$lot->remarques ?? null" class="sm:col-span-2"/>
        </div>

        @unless ($lot)
            <label class="flex items-start gap-3 rounded-lg border border-stone-200 p-3 text-sm text-stone-700">
                <input type="checkbox" name="programme_soins" value="1" @checked(old('programme_soins')) class="mt-0.5 size-5">
                <span><strong>Générer le programme de soins poussins</strong> de l’espèce et les 28 réglages de température (34 °C → 27 °C). Si le programme est absent ou incomplet, rien n’est créé.</span>
            </label>
        @endunless

        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ $lot ? route('lots.show', $lot->id) : route('lots.index') }}" class="btn-secondaire">Annuler</a>
            <button type="submit" class="btn-primaire">{{ $lot ? 'Enregistrer' : 'Créer le lot' }}</button>
        </div>
    </form>
</x-layouts.app>

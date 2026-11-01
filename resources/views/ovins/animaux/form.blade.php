@php
    use App\Support\Empreinte; use App\Support\Montant;
    $opt = fn ($c) => $c->mapWithKeys(fn ($x) => [$x->id_animal => ($x->code_animal ?: '#'.$x->id_animal).($x->nom_animal ? ' · '.$x->nom_animal : '').($x->statut !== 'en_ferme' ? ' ('.\App\Domaine\Ovins\Animaux::statut($x->statut).')' : '')])->all();
@endphp
<x-layouts.app :titre="$a ? 'Modifier '.($a->code_animal ?: '#'.$a->id_animal) : 'Nouvel animal'" :sous-titre="$a ? 'Identité, filiation et observations. Le lot, le site et le statut changent par un transfert ou une sortie.' : 'Fiche individuelle'">
    <form method="POST" action="{{ $a ? route('ovins.animaux.update', $a->id_animal) : route('ovins.animaux.store') }}" class="carte max-w-3xl space-y-6 p-5 sm:p-6">
        @csrf
        @if ($a) @method('PUT') <input type="hidden" name="empreinte" value="{{ Empreinte::de($a) }}"> @else <x-cle/> @endif

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 font-semibold text-stone-900">Identité</legend>
            <x-champ name="code_animal" label="Numéro de boucle" :value="$a?->code_animal" maxlength="100" :aide="$a ? null : 'Laissez vide pour un numéro automatique.'"/>
            <x-champ name="nom_animal" label="Nom" :value="$a?->nom_animal" maxlength="50"/>
            <x-choix name="sexe" label="Sexe" :options="['F' => 'Femelle', 'M' => 'Mâle']" :value="$a?->sexe" vide="Choisir…" required/>
            <x-champ name="race" label="Race" :value="$a?->race" maxlength="100" list="races"/>
            <x-champ name="date_naissance" label="Date de naissance" type="date" :value="$a?->date_naissance" :max="today()->toDateString()"/>
            <x-champ name="date_entree" label="Date d’entrée" type="date" :value="$a?->date_entree ?? today()->toDateString()" :max="today()->toDateString()"/>
            <x-champ name="origine" label="Origine" :value="$a?->origine" maxlength="100" placeholder="Achat marché de Dahra, naissance…"/>
            <x-choix name="id_proprietaire" label="Propriétaire" :options="$proprietaires->mapWithKeys(fn ($p) => [$p->id_proprietaire => $p->nom_proprietaire])->all()" :value="$a?->id_proprietaire" vide="Aucun"/>
        </fieldset>
        <datalist id="races"><option value="Ladoum"><option value="Touabire"><option value="Peul-peul"><option value="Bali-bali"><option value="Djallonké"></datalist>

        @unless ($a)
            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 font-semibold text-stone-900">Emplacement</legend>
                <x-choix name="id_site" label="Site" :options="$sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()" vide="Choisir…" required/>
                <x-choix name="id_lot" label="Lot" :options="$lots->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_site.' · '.$l->nom_lot])->all()" vide="Sans lot" aide="Le lot doit appartenir au site choisi."/>
                <label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" name="compter" value="1" class="mt-0.5 size-5" @checked(old('compter', '1'))>
                    <span>Animal qui arrive : l’ajouter à l’effectif du lot <span class="block text-xs text-stone-500">Décochez si l’animal est déjà compté dans l’effectif du lot et que vous créez seulement sa fiche.</span></span></label>
            </fieldset>
        @endunless

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 font-semibold text-stone-900">Filiation</legend>
            <x-choix name="id_mere" label="Mère" :options="$opt($meres)" :value="$a?->id_mere" vide="Inconnue"/>
            <x-choix name="id_pere" label="Père" :options="$opt($peres)" :value="$a?->id_pere" vide="Inconnu"/>
        </fieldset>

        <fieldset class="grid gap-4 sm:grid-cols-3">
            <legend class="mb-2 font-semibold text-stone-900">Mesures et valeur</legend>
            <x-champ name="poids_naissance" label="Poids à la naissance" suffixe="kg" inputmode="decimal" :value="$a?->poids_naissance"/>
            <x-champ name="poids_dernier" label="Dernier poids" suffixe="kg" inputmode="decimal" :value="$a?->poids_dernier"/>
            <x-champ name="prix_achat" label="Prix d’achat" suffixe="FCFA" inputmode="decimal" :value="$a?->prix_achat !== null ? Montant::champ(Montant::base($a->prix_achat)) : null"/>
        </fieldset>

        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="est_reproducteur" value="1" class="size-5" @checked(old('est_reproducteur', $a?->est_reproducteur))> Reproducteur ou reproductrice</label>
        <x-champ name="observations" label="Observations" type="textarea" :value="$a?->observations"/>

        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ $a ? route('ovins.animaux.show', $a->id_animal) : route('ovins.animaux.index') }}" class="btn-secondaire">Annuler</a>
            <button class="btn-primaire">{{ $a ? 'Enregistrer' : 'Créer la fiche' }}</button>
        </div>
    </form>
</x-layouts.app>

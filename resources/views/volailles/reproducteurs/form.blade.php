<x-layouts.app :titre="$bande ? 'Modifier '.$bande->nom_bande : 'Nouvelle bande'">
    <form method="POST" action="{{ $bande ? route('reproducteurs.update', $bande->id) : route('reproducteurs.store') }}" class="carte max-w-2xl space-y-5 p-5 sm:p-6">
        @csrf
        @if ($bande)
            @method('PUT')
            <input type="hidden" name="empreinte" value="{{ \App\Support\Empreinte::de($bande) }}">
        @else
            <x-cle/>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-champ name="nom_bande" label="Nom de la bande" :value="$bande->nom_bande ?? null" required maxlength="100" class="sm:col-span-2"/>
            @unless ($bande)
                <x-choix name="espece" label="Espèce" :options="['poulet' => 'Poulet', 'caille' => 'Caille']"/>
                <x-champ name="date_mise_en_place" label="Mise en place" type="date" :max="today()->toDateString()"/>
                <x-champ name="nb_femelles" label="Femelles" type="number" min="0" required inputmode="numeric"/>
                <x-champ name="nb_males" label="Mâles" type="number" min="0" required inputmode="numeric"/>
            @else
                <x-champ name="date_mise_en_place" label="Mise en place" type="date" :value="$bande->date_mise_en_place" :max="today()->toDateString()"/>
                <div class="flex items-end">
                    <label class="flex min-h-11 items-center gap-3">
                        <input type="hidden" name="reformee" value="0">
                        <input type="checkbox" name="reformee" value="1" @checked(old('reformee', $bande->reformee)) class="size-5">
                        <span class="text-sm font-medium text-stone-700">Bande réformée (retirée de la production)</span>
                    </label>
                </div>
                <p class="text-xs text-stone-500 sm:col-span-2">Les effectifs se modifient uniquement par un mouvement (entrée, retrait, mortalité), depuis la fiche de la bande.</p>
            @endunless
            <x-champ name="observations" label="Observations" type="textarea" :value="$bande->observations ?? null" class="sm:col-span-2"/>
        </div>
        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ $bande ? route('reproducteurs.show', $bande->id) : route('reproducteurs.index') }}" class="btn-secondaire">Annuler</a>
            <button type="submit" class="btn-primaire">{{ $bande ? 'Enregistrer' : 'Créer la bande' }}</button>
        </div>
    </form>
</x-layouts.app>

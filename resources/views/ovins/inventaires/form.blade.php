<x-layouts.app titre="Ouvrir un inventaire" sous-titre="La liste des animaux attendus est figée à l’ouverture">
    <form method="GET" class="carte mb-4 grid max-w-2xl gap-3 p-4 sm:grid-cols-3" onchange="this.submit()">
        <x-choix name="site" label="Site" :options="$sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()" :value="$f['site'] ?? null" vide="Tous"/>
        <x-choix name="lot" label="Lot" :options="$lots->when($f['site'] ?? null, fn ($c, $s) => $c->where('id_site', $s))->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_lot])->all()" :value="$f['lot'] ?? null" vide="Tous"/>
        <x-choix name="proprietaire" label="Propriétaire" :options="$proprietaires->mapWithKeys(fn ($p) => [$p->id_proprietaire => $p->nom_proprietaire])->all()" :value="$f['proprietaire'] ?? null" vide="Tous"/>
        <noscript><button class="btn-secondaire">Compter</button></noscript>
    </form>
    <form method="POST" action="{{ route('ovins.inventaires.store') }}" class="carte max-w-2xl space-y-4 p-4">
        @csrf <x-cle/>
        @foreach (['site', 'lot', 'proprietaire'] as $k)<input type="hidden" name="{{ $k }}" value="{{ $f[$k] ?? '' }}">@endforeach
        <p class="text-sm"><strong class="text-2xl tabular-nums">{{ $compte }}</strong> animal(aux) en ferme dans ce périmètre.</p>
        <x-champ name="titre" label="Titre" required maxlength="150" :value="'Inventaire du '.today()->format('d/m/Y')"/>
        <button class="btn-primaire" @disabled($compte < 1)>Ouvrir l’inventaire</button>
    </form>
</x-layouts.app>

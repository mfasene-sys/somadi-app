@php $peut = \App\Domaine\Organisation\Activites::planificateur(); $noms = $utilisateurs->mapWithKeys(fn ($u) => [$u->id => trim($u->prenom.' '.$u->nom)]); @endphp
<x-layouts.app titre="Équipes et catégories">
    @include('organisation.activites._onglets')
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="space-y-3">
            <h2 class="font-semibold text-stone-900">Équipes</h2>
            @foreach ($liste as $e)
                <details class="carte {{ $e->actif ? '' : 'opacity-60' }}">
                    <summary class="flex cursor-pointer list-none items-center gap-3 p-4"><span class="size-3 rounded-full" style="background: {{ $e->couleur }}"></span><span class="flex-1 font-medium">{{ $e->nom }}</span>
                        <span class="text-xs text-stone-500">{{ collect($membres[$e->id] ?? [])->map(fn ($m) => $noms[$m->id_user] ?? '#'.$m->id_user)->implode(', ') ?: 'aucun membre' }}</span></summary>
                    @if ($peut)
                        <form method="POST" action="{{ route('activites.equipes.enregistrer', $e->id) }}" class="space-y-3 border-t border-stone-100 p-4">@csrf
                            @include('organisation.activites._equipe', ['e' => $e, 'sel' => collect($membres[$e->id] ?? [])->pluck('id_user')->all()])
                            <button class="btn-secondaire w-full">Enregistrer</button></form>
                    @endif
                </details>
            @endforeach
            @if ($peut)
                <form method="POST" action="{{ route('activites.equipes.enregistrer') }}" class="carte space-y-3 p-4">@csrf
                    <h3 class="font-semibold">Nouvelle équipe</h3>
                    @include('organisation.activites._equipe', ['e' => null, 'sel' => []])
                    <button class="btn-primaire w-full">Créer</button></form>
            @endif
            <p class="text-xs text-stone-500">Une équipe se désactive plutôt que d’être supprimée. Retirer un membre lui retire l’accès aux tâches de l’équipe, sans effacer ses pointages.</p>
        </section>
        <section class="space-y-3">
            <h2 class="font-semibold text-stone-900">Catégories</h2>
            <div class="carte divide-y divide-stone-100 text-sm">
                @foreach ($toutesCategories as $c)
                    <form method="POST" action="{{ route('activites.categories.enregistrer', $c->id) }}" class="flex items-center gap-2 px-4 py-2 {{ $c->actif ? '' : 'opacity-60' }}">@csrf
                        <input type="color" name="couleur" value="{{ $c->couleur ?: '#a8a29e' }}" class="h-8 w-10 rounded border border-stone-300" @disabled(! $peut)>
                        <input name="libelle" value="{{ $c->libelle }}" class="champ min-h-9 flex-1" @disabled(! $peut)>
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="actif" value="1" @checked($c->actif) @disabled(! $peut)> Active</label>
                        @if ($peut)<button class="btn-secondaire min-h-9 text-xs">OK</button>@endif
                    </form>
                @endforeach
            </div>
            @if ($peut)
                <form method="POST" action="{{ route('activites.categories.enregistrer') }}" class="carte flex items-end gap-2 p-4">@csrf
                    <x-champ name="libelle" label="Nouvelle catégorie" required maxlength="100" class="flex-1" id="cat_new"/><input type="hidden" name="actif" value="1">
                    <button class="btn-primaire">Ajouter</button></form>
            @endif
        </section>
    </div>
</x-layouts.app>

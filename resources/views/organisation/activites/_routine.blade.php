<x-champ name="titre" label="Titre" :value="$r?->titre" required maxlength="200"/>
<x-choix name="id_responsable" label="Responsable" :options="$utilisateurs->mapWithKeys(fn ($u) => [$u->id => trim($u->prenom.' '.$u->nom)])->all()" :value="$r?->id_responsable" vide="Aucun"/>
<x-choix name="id_equipe" label="Équipe exécutante" :options="$equipes->mapWithKeys(fn ($e) => [$e->id => $e->nom])->all()" :value="$r?->id_equipe" vide="Aucune"/>
<div class="grid grid-cols-2 gap-3">
    <x-champ name="date_debut" label="Valable du" type="date" :value="$r?->date_debut"/>
    <x-champ name="date_fin" label="au" type="date" :value="$r?->date_fin"/>
</div>
<x-champ name="description" label="Description" type="textarea" :value="$r?->description"/>
<div class="flex items-center gap-4"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="actif" value="1" class="size-5" @checked($r?->actif ?? true)> Active</label>
    <label class="flex items-center gap-2 text-sm">Couleur <input type="color" name="couleur_fond" value="{{ $r?->couleur_fond ?? '#EAF4FF' }}" class="h-9 w-14 rounded border border-stone-300"></label></div>

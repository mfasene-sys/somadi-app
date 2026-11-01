@php $x = $e ? 'e'.$e->id : 'n'; @endphp
<x-champ name="nom" label="Nom" :value="$e?->nom" required maxlength="120" :id="$x.'nom'"/>
<div><label class="etiquette" for="{{ $x }}m">Membres</label><select id="{{ $x }}m" name="membres[]" multiple class="champ h-32 py-1">@foreach ($utilisateurs as $u)<option value="{{ $u->id }}" @selected(in_array($u->id, $sel))>{{ trim($u->prenom.' '.$u->nom) }}</option>@endforeach</select></div>
<div class="flex items-center gap-4"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="actif" value="1" class="size-5" @checked($e?->actif ?? true)> Active</label>
    <label class="flex items-center gap-2 text-sm">Couleur <input type="color" name="couleur" value="{{ $e?->couleur ?? '#2d89ef' }}" class="h-9 w-14 rounded border border-stone-300"></label></div>

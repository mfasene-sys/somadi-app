@php $x = $m ? 'm'.$m->id : 'n'; @endphp
<x-champ name="titre" label="Titre" :value="$m?->titre" required maxlength="120" :id="$x.'t'"/>
<x-champ name="message" label="Message" type="textarea" :value="$m?->message" required rows="5" :id="$x.'m'" placeholder="Bonjour {client_nom}, …"/>
<div><label class="etiquette" for="{{ $x }}i">Image (facultative)</label><input id="{{ $x }}i" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
    @if ($m?->image_path)<a href="{{ route('marketing.modeles.image', $m->id) }}" target="_blank" class="text-xs text-ferme-700">Image actuelle</a>@endif</div>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_actif" value="1" class="size-5" @checked($m?->is_actif ?? true)> Actif</label>

<x-champ name="nom_proprietaire" label="Nom" :value="$p?->nom_proprietaire" required maxlength="120" :id="$x.'nom'"/>
<x-champ name="telephone" label="Téléphone" type="tel" :value="$p?->telephone" maxlength="30" :id="$x.'tel'"/>
<div><label class="etiquette" for="{{ $x }}coul">Couleur</label><input id="{{ $x }}coul" type="color" name="couleur_hex" value="{{ old('couleur_hex', $p?->couleur_hex ?: '#16a34a') }}" class="h-11 w-full cursor-pointer rounded-lg border border-stone-300 bg-white p-1"></div>
<label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="actif" value="1" class="size-5" @checked($p?->actif ?? true)> Actif</label>
<x-champ name="observations" label="Observations" :value="$p?->observations" :id="$x.'obs'" class="sm:col-span-2"/>

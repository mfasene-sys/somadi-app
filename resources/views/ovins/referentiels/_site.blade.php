<x-champ name="code_site" label="Code" :value="$s?->code_site" required maxlength="50" :id="$p.'code'"/>
<x-champ name="nom_site" label="Nom" :value="$s?->nom_site" required maxlength="150" :id="$p.'nom'"/>
<x-champ name="localisation" label="Localisation" :value="$s?->localisation" maxlength="255" :id="$p.'loc'"/>
<x-champ name="type_site" label="Type" :value="$s?->type_site" maxlength="50" :id="$p.'type'" placeholder="Bergerie, pâturage…"/>
<x-champ name="capacite_ovins" label="Capacité" type="number" min="0" :value="$s?->capacite_ovins ?? 0" :id="$p.'cap'"/>
<label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="actif" value="1" class="size-5" @checked($s?->actif ?? true)> Actif</label>
<x-champ name="observations" label="Observations" :value="$s?->observations" :id="$p.'obs'" class="sm:col-span-2"/>

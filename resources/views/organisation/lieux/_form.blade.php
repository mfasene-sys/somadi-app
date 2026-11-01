@php use App\Domaine\Organisation\Infrastructures as I; $x = $prefixe ?? ''; @endphp
<x-champ name="code" label="Code" :value="$l?->code" required maxlength="40" :id="$x.'code'"/>
<x-champ name="nom_batiment" label="Nom" :value="$l?->nom_batiment" required maxlength="100" :id="$x.'nom'"/>
<x-choix name="id_site" label="Site" :options="$sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()" :value="$l?->id_site" vide="Choisir…" required :id="$x.'site'"/>
<x-choix name="type_lieu" label="Type" :options="I::TYPES" :value="$l?->type_lieu ?? 'batiment'" :id="$x.'type'"/>
<x-choix name="famille" label="Famille accueillie" :options="I::FAMILLES" :value="$l && $l->famille !== 'a_configurer' ? $l->famille : null" vide="Choisir…" required :id="$x.'fam'"/>
<x-champ name="espece" label="Espèce (vide : toutes)" :value="$l?->espece" maxlength="50" :id="$x.'esp'" placeholder="poulet, caille…"/>
<x-champ name="stade" label="Stade" :value="$l?->stade" maxlength="80" :id="$x.'stade'" placeholder="démarrage, finition…"/>
<x-champ name="capacite" label="Capacité" type="number" min="1" :value="$l?->capacite" required :id="$x.'cap'"/>
<x-champ name="surface_m2" label="Surface" suffixe="m²" inputmode="decimal" :value="$l?->surface_m2" :id="$x.'surf'"/>
<x-choix name="etat" label="État" :options="collect(I::ETATS)->map(fn ($e) => $e[0])->all()" :value="$l?->etat ?? 'disponible'" :id="$x.'etat'"/>
<x-champ name="disponible_le" label="Disponible le (si indisponible)" type="date" :value="$l?->disponible_le" :id="$x.'dispo'"/>
<label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="actif" value="1" class="size-5" @checked($l?->actif ?? true)> Actif</label>
<x-champ name="observations" label="Observations" :value="$l?->observations" :id="$x.'obs'" class="sm:col-span-2"/>

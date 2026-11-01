<x-champ name="nom_soin" label="Nom" :value="$t?->nom_soin" required maxlength="100" :id="$x.'nom'"/>
<x-champ name="categorie" label="Catégorie" :value="$t?->categorie" maxlength="50" :id="$x.'cat'" placeholder="Vaccin, déparasitage…"/>
<x-champ name="voie" label="Voie" :value="$t?->voie" maxlength="50" :id="$x.'voie'" placeholder="Orale, injection…"/>
<x-champ name="delai_attente_jours" label="Délai d’attente" type="number" min="0" suffixe="j" :value="$t?->delai_attente_jours" :id="$x.'del'"/>
<x-champ name="commentaire" label="Commentaire" :value="$t?->commentaire" :id="$x.'com'" class="sm:col-span-2"/>

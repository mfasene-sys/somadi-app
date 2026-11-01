@php $x = $p ? 'p'.$p->id : 'n'; @endphp
<x-champ name="titre" label="Titre" :value="$p?->titre" required maxlength="200" :id="$x.'t'"/>
<x-champ name="code" label="Code" :value="$p?->code" maxlength="30" :id="$x.'c'"/>
<x-champ name="date_debut" label="Début" type="date" :value="$p?->date_debut" :id="$x.'d'"/>
<x-champ name="date_cible" label="Date cible" type="date" :value="$p?->date_cible" :id="$x.'dc'"/>
<x-choix name="statut" label="État" :options="['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé']" :value="$p?->statut ?? 'planifie'" :id="$x.'s'"/>
<x-choix name="priorite" label="Priorité" :options="collect(\App\Domaine\Organisation\Activites::PRIORITES)->map(fn ($v) => $v[0])->all()" :value="$p?->priorite ?? 'normale'" :id="$x.'pr'"/>
<x-choix name="id_responsable" label="Responsable" :options="$utilisateurs->mapWithKeys(fn ($u) => [$u->id => trim($u->prenom.' '.$u->nom)])->all()" :value="$p?->id_responsable" vide="Aucun" :id="$x.'r'"/>
<x-choix name="id_equipe" label="Équipe" :options="$equipes->mapWithKeys(fn ($e) => [$e->id => $e->nom])->all()" :value="$p?->id_equipe" vide="Aucune" :id="$x.'e'"/>
<x-champ name="description" label="Description" type="textarea" :value="$p?->description" :id="$x.'desc'" class="sm:col-span-2"/>

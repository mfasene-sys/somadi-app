@php($ton = ['en_ferme' => 'ok', 'vendu' => 'info', 'mort' => 'alerte'][$statut] ?? 'neutre')
<x-statut :ton="$ton">{{ \App\Domaine\Ovins\Animaux::statut($statut) }}</x-statut>

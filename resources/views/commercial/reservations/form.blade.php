@php use App\Support\Format; @endphp
<x-layouts.app titre="Nouvelle réservation" sous-titre="La quantité est retirée du disponible du lot jusqu’à l’éclosion">
    @if ($previsions->isEmpty())
        <x-vide icone="calendar">Aucun lot en incubation n’a de poussins disponibles à réserver.</x-vide>
    @else
        <form method="POST" action="{{ route('reservations.store') }}" class="carte max-w-2xl space-y-5 p-5 sm:p-6">
            @csrf <x-cle/>
            <x-choix name="id_lot_prevu" label="Lot en incubation" required :value="$choisi"
                     :options="$previsions->mapWithKeys(fn ($p) => [$p->id_lot => $p->nom_lot.' · éclosion '.Format::date($p->date_eclosion_prevue).' · '.$p->nombre_prevu.' dispo'])->all()"/>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ name="nom_client" label="Client" required maxlength="100"/>
                <x-champ name="telephone" label="Téléphone" type="tel" required inputmode="tel"/>
                <x-champ name="quantite" label="Nombre de poussins" type="number" min="1" required inputmode="numeric"/>
                <x-champ name="prix_unitaire" label="Prix unitaire" suffixe="FCFA" required inputmode="numeric"/>
                <x-champ name="acompte" label="Acompte reçu" suffixe="FCFA" inputmode="numeric" aide="Noté sur la réservation ; il devient un paiement à la livraison si vous le cochez."/>
            </div>
            <x-champ name="commentaire" label="Commentaire" type="textarea"/>
            <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('reservations.index') }}" class="btn-secondaire">Annuler</a>
                <button class="btn-primaire">Réserver</button>
            </div>
        </form>
    @endif
</x-layouts.app>

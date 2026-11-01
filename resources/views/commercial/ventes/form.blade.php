<x-layouts.app titre="Nouvelle vente" sous-titre="Volailles et autres produits (les œufs ont leur propre onglet)">
    @include('commercial.ventes._onglets')
    <form method="POST" action="{{ route('ventes.store') }}" class="carte max-w-3xl space-y-5 p-5 sm:p-6">
        @csrf <x-cle/>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-champ name="client_nom" label="Client" required maxlength="100" autocomplete="off"/>
            <x-champ name="client_tel" label="Téléphone" type="tel" required maxlength="20" inputmode="tel" placeholder="77 123 45 67"/>
            <x-choix name="produit" label="Produit" :options="$produits->mapWithKeys(fn ($p, $k) => [$k => $p['libelle']])->all()" required/>
            <x-champ name="date_vente" label="Date de vente" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
            <x-champ name="quantite" label="Quantité" type="number" min="1" required inputmode="numeric"/>
            <x-champ name="prix_unitaire" label="Prix unitaire" suffixe="FCFA" required inputmode="decimal"/>
            <x-choix name="id_lot" label="Lot de volailles (sortie)" :options="$lots->mapWithKeys(fn ($l) => [$l->id => $l->nom_lot.' — '.$l->effectif_en_cours.' sujets'])->all()" vide="Aucun lot (vente hors élevage)" class="sm:col-span-2"
                     aide="Si un lot est choisi, ses sujets sortent immédiatement : l’effectif baisse avec la vente."/>
            <x-champ name="commentaire" label="Commentaire" type="textarea" class="sm:col-span-2"/>
        </div>
        @include('commercial._encaissement-immediat')
        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('ventes.index') }}" class="btn-secondaire">Annuler</a>
            <button class="btn-primaire">Enregistrer la vente</button>
        </div>
    </form>
</x-layouts.app>

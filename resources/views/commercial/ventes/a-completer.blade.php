@php use App\Support\Format; @endphp
<x-layouts.app titre="Sorties à compléter" sous-titre="Volailles sorties en vente depuis un lot : il reste à saisir le client et le prix">
    @include('commercial.ventes._onglets')

    @if ($sorties->isEmpty())
        <x-vide icone="check">Aucune sortie en attente.</x-vide>
    @else
        <div class="space-y-4">
            @foreach ($sorties as $s)
                <details class="carte group" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 p-4">
                        <span class="tabular-nums text-stone-500">{{ Format::date($s->date_vente) }}</span>
                        <span class="font-medium text-stone-900">{{ $s->nom_lot }}</span>
                        <span class="text-sm text-stone-500">{{ $s->commentaire }}</span>
                        <span class="ml-auto font-semibold tabular-nums">{{ $s->quantite }} sujets</span>
                        <x-icone nom="chevron-right" class="size-4 text-stone-400 transition group-open:rotate-90"/>
                    </summary>
                    <form method="POST" action="{{ route('ventes.finaliser', $s->id) }}" class="space-y-4 border-t border-stone-100 p-4">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-champ name="client_nom" label="Client" required maxlength="100" :id="'c'.$s->id"/>
                            <x-champ name="client_tel" label="Téléphone" type="tel" required inputmode="tel" :id="'t'.$s->id"/>
                            <x-choix name="produit" label="Produit" :options="$produits->mapWithKeys(fn ($p, $k) => [$k => $p['libelle']])->all()" :value="str_contains(mb_strtolower((string) $s->race), 'goliath') ? 'goliath_chair' : 'poulet_chair'" :id="'p'.$s->id"/>
                            <x-champ name="quantite" label="Quantité (contrôle)" type="number" min="1" required inputmode="numeric" :id="'q'.$s->id" :aide="'Doit être '.$s->quantite.', la quantité sortie.'"/>
                            <x-champ name="prix_unitaire" label="Prix unitaire" suffixe="FCFA" required inputmode="decimal" :id="'u'.$s->id"/>
                            <x-champ name="commentaire" label="Commentaire" :value="$s->commentaire" :id="'m'.$s->id"/>
                        </div>
                        @include('commercial._encaissement-immediat')
                        <div class="flex justify-end"><button class="btn-primaire">Finaliser la vente</button></div>
                    </form>
                </details>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-stone-500">La date et la quantité sont celles de la sortie ; l’effectif du lot a déjà été débité et ne l’est pas une seconde fois.</p>
    @endif
</x-layouts.app>

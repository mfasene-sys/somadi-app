@php
    use App\Support\Format; use App\Support\Montant; use App\Domaine\Reservations;
    [$lib, $ton] = Reservations::libelle($r->statut); $st = Reservations::normalise($r->statut);
    $total = (int) $r->quantite * (int) $r->prix_unitaire;
    $produitDefaut = $r->espece && str_contains(mb_strtolower($r->espece), 'goliath') ? 'poussin_goliath' : 'poussin_chair';
@endphp
<x-layouts.app :titre="'Réservation n° '.$r->id_reservation" :sous-titre="$r->nom_client.' · '.$r->telephone">
    <x-slot:actions>
        <x-statut :ton="$ton" class="px-3 py-1 text-sm">{{ $lib }}</x-statut>
        <button type="button" onclick="window.print()" class="btn-secondaire print:hidden">Imprimer</button>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="carte space-y-2 p-5 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">SOMADI Farming · bon de réservation</p>
            <dl class="grid grid-cols-[9rem_1fr] gap-y-2">
                <dt class="text-stone-500">Date</dt><dd>{{ Format::dateLongue($r->date_reservation) }}</dd>
                <dt class="text-stone-500">Lot</dt><dd>{{ $r->nom_lot ?? '—' }} · {{ ucfirst((string) $r->espece) }}</dd>
                <dt class="text-stone-500">Éclosion</dt><dd>{{ $r->date_eclosion ? 'le '.Format::date($r->date_eclosion) : 'prévue le '.Format::date($r->date_eclosion_previsionnelle) }}</dd>
                <dt class="text-stone-500">Quantité</dt><dd class="tabular-nums"><strong>{{ $r->quantite }}</strong> poussin(s){{ $r->quantite_servie ? ' · '.$r->quantite_servie.' affecté(s)' : '' }}</dd>
                <dt class="text-stone-500">Prix unitaire</dt><dd class="tabular-nums">{{ Montant::fcfa((int) $r->prix_unitaire * 100) }}</dd>
                <dt class="text-stone-500">Total</dt><dd class="tabular-nums font-semibold">{{ Montant::fcfa($total * 100) }}</dd>
                <dt class="text-stone-500">Acompte</dt><dd class="tabular-nums">{{ Montant::fcfa((int) $r->acompte * 100) }}</dd>
                @if ($r->date_livraison)<dt class="text-stone-500">Livrée le</dt><dd>{{ Format::date($r->date_livraison) }}</dd>@endif
            </dl>
            @if ($r->commentaire)<p class="whitespace-pre-line border-t border-stone-100 pt-2 text-stone-600">{{ $r->commentaire }}</p>@endif
        </section>

        @can('RES.gerer')
            <div class="space-y-4 print:hidden">
                @if (in_array($st, ['confirmé', Reservations::PARTIEL], true))
                    <form method="POST" action="{{ route('reservations.livrer', $r->id_reservation) }}" class="carte space-y-3 p-4">
                        @csrf
                        <h2 class="font-semibold text-stone-900">Livrer {{ $r->quantite_servie }} poussin(s)</h2>
                        <x-champ name="date_livraison" label="Date de livraison" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                        @can('VEN.gerer')
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="creer_vente" value="1" class="size-5" checked onchange="document.getElementById('bloc-vente').hidden = ! this.checked"> Créer la vente correspondante</label>
                            <div id="bloc-vente" class="space-y-3">
                                <x-choix name="produit" label="Produit" :options="$produits->mapWithKeys(fn ($p, $k) => [$k => $p['libelle']])->all()" :value="$produitDefaut"/>
                                @if ($lotVolaille && count($origines) > 1)
                                    <x-choix name="origine" label="Emplacement de sortie" :options="collect($origines)->mapWithKeys(fn ($o) => [$o['id'] => $o['nom'].' ('.$o['quantite'].')'])->all()" vide="—"/>
                                @endif
                                @if ($lotVolaille)<p class="text-xs text-stone-500">Les poussins sortent du lot {{ $lotVolaille->nom_lot }}.</p>@endif
                                @if ((int) $r->acompte > 0)
                                    @can('CPT.encaisser')
                                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="encaisser_acompte" value="1" class="size-5"> Enregistrer l’acompte de {{ Montant::fcfa((int) $r->acompte * 100) }} comme paiement</label>
                                        <x-choix name="moyen_paiement" label="Moyen de paiement de l’acompte" :options="array_combine($moyens, $moyens)"/>
                                    @endcan
                                @endif
                            </div>
                        @endcan
                        <button class="btn-primaire w-full">Enregistrer la livraison</button>
                    </form>
                @endif
                @if (! in_array($st, ['livré', 'annulé'], true))
                    <details class="carte p-4">
                        <summary class="cursor-pointer text-sm font-medium text-red-700">Annuler la réservation</summary>
                        <form method="POST" action="{{ route('reservations.annuler', $r->id_reservation) }}" class="mt-3 space-y-3">
                            @csrf
                            <x-champ name="motif" label="Motif" required maxlength="500"/>
                            <button class="btn-secondaire w-full">Confirmer l’annulation</button>
                        </form>
                    </details>
                @endif
            </div>
        @endcan
    </div>
</x-layouts.app>

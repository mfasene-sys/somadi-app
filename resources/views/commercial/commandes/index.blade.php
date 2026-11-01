@php use App\Support\Format; use App\Support\Montant; @endphp
<x-layouts.app titre="Commandes (historique)" sous-titre="Registres de l’ancienne application, conservés en lecture seule">
    <p class="mb-4 rounded-lg bg-stone-100 px-4 py-3 text-sm text-stone-600">Les nouvelles commandes passent par les <a href="{{ route('reservations.index') }}" class="font-medium text-ferme-700 underline">réservations</a> et les <a href="{{ route('ventes.index') }}" class="font-medium text-ferme-700 underline">ventes</a>.</p>

    <section class="mb-8">
        <h2 class="mb-3 font-semibold text-stone-900">Commandes de vente <span class="font-normal text-stone-500">· {{ $commandesVente->count() }}</span></h2>
        @if ($commandesVente->isEmpty())
            <p class="carte px-4 py-3 text-sm text-stone-500">Aucune.</p>
        @else
            <div class="carte overflow-x-auto">
                <table class="tableau">
                    <thead class="bg-stone-50"><tr><th>N°</th><th>Réservée le</th><th>Client</th><th>Produit</th><th class="text-right">Qté</th><th class="text-right">Total</th><th class="text-right">Payé</th><th class="text-right">Solde</th><th>Statut</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($commandesVente as $c)
                            @php($total = Montant::base($c->prix_total)) @php($paye = Montant::base($c->acompte_verse) + Montant::base($c->paye ?? 0)) @php($solde = Montant::base($c->solde_restant))
                            <tr>
                                <td class="tabular-nums">{{ $c->id }}</td>
                                <td class="tabular-nums">{{ Format::date($c->date_reservation) }}</td>
                                <td>{{ $c->nom_client }}<span class="block text-xs text-stone-500">{{ $c->telephone_client }}</span></td>
                                <td>{{ $c->type_produit }}</td>
                                <td class="text-right tabular-nums">{{ $c->quantite }}</td>
                                <td class="text-right tabular-nums">{{ Montant::fcfa($total) }}</td>
                                <td class="text-right tabular-nums">{{ Montant::fcfa($paye) }}</td>
                                <td class="text-right tabular-nums">{{ Montant::fcfa($solde) }}
                                    @if ($total - $paye !== $solde)<span class="block text-xs text-paille-700" title="Total − acompte − paiements = {{ Montant::fcfa($total - $paye) }}">écart</span>@endif</td>
                                <td><x-statut>{{ str_replace('_', ' ', $c->statut_commande ?: $c->statut) }}</x-statut></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mb-8">
        <h2 class="mb-3 font-semibold text-stone-900">Commandes simples <span class="font-normal text-stone-500">· {{ $commandes->count() }}</span></h2>
        @if ($commandes->isEmpty())
            <p class="carte px-4 py-3 text-sm text-stone-500">Aucune.</p>
        @else
            <div class="carte divide-y divide-stone-100 text-sm">
                @foreach ($commandes as $c)
                    <div class="flex flex-wrap gap-3 px-4 py-2.5"><span class="tabular-nums text-stone-500">{{ Format::date($c->date_commande) }}</span><span class="flex-1">{{ $c->nom_client }} · {{ $c->telephone }} · {{ $c->produit }}</span><strong class="tabular-nums">{{ $c->quantite }}</strong><x-statut>{{ $c->statut }}</x-statut></div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($relances->isNotEmpty())
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Dernières relances</h2>
            <div class="carte divide-y divide-stone-100 text-sm">
                @foreach ($relances as $rl)
                    <div class="flex gap-3 px-4 py-2"><span class="tabular-nums text-stone-500">{{ Format::date(substr($rl->date_heure, 0, 10)) }}</span><span class="flex-1">Commande n° {{ $rl->id_commande }} · {{ $rl->canal }}</span><span class="text-stone-500">{{ $rl->utilisateur }}</span></div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>

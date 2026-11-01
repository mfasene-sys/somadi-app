@php use App\Support\Montant; use App\Support\Format; @endphp
{{-- $b : bilan (tableau de Exercices::bilan) ; montants en centimes. --}}
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-indicateur label="Ventes facturées" :valeur="Montant::fcfa($b['ventes_total'])" :detail="Format::nombre($b['ventes_nombre']).' vente(s)'"/>
    <x-indicateur label="Recettes" :valeur="Montant::fcfa($b['recettes'])"/>
    <x-indicateur label="Dépenses" :valeur="Montant::fcfa($b['depenses'])"/>
    <x-indicateur label="Mouvement net" :valeur="Montant::fcfa($b['mouvement_net'])" :ton="$b['mouvement_net'] < 0 ? 'alerte' : null"/>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section>
        <h3 class="mb-3 font-semibold text-stone-900">Ventes par produit</h3>
        <div class="carte divide-y divide-stone-100 text-sm">
            @forelse ($b['ventes'] as $v)
                <div class="flex justify-between gap-3 px-4 py-2.5"><span>{{ $v['libelle'] }} <span class="text-xs text-stone-400">{{ Format::nombre($v['quantite']) }} u. · {{ $v['n'] }} vente(s)</span></span><strong class="tabular-nums">{{ Montant::fcfa($v['total']) }}</strong></div>
            @empty <p class="px-4 py-3 text-stone-500">Aucune vente.</p> @endforelse
        </div>
    </section>
    <section>
        <h3 class="mb-3 font-semibold text-stone-900">Recettes et dépenses par catégorie</h3>
        <div class="carte divide-y divide-stone-100 text-sm">
            @forelse ($b['operations'] as $o)
                <div class="flex justify-between gap-3 px-4 py-2.5"><span><span class="text-xs {{ $o['type'] === 'recette' ? 'text-ferme-700' : 'text-paille-700' }}">{{ $o['type'] === 'recette' ? 'Recette' : 'Dépense' }}</span> · {{ $o['categorie'] }}</span><strong class="tabular-nums">{{ Montant::fcfa($o['total']) }}</strong></div>
            @empty <p class="px-4 py-3 text-stone-500">Aucune opération.</p> @endforelse
        </div>
        @if ($b['recettes_ventes_anterieures']['n'])
            <p class="mt-2 text-xs text-stone-500">Dont {{ Montant::fcfa($b['recettes_ventes_anterieures']['total']) }} encaissés sur {{ $b['recettes_ventes_anterieures']['n'] }} vente(s) d’exercices antérieurs.</p>
        @endif
    </section>
</div>

<section class="mt-6">
    <h3 class="mb-3 font-semibold text-stone-900">Créances au {{ Format::date($b['arrete']) }} <span class="font-normal text-stone-500">· {{ Montant::fcfa($b['creances_total']) }}</span></h3>
    @if (empty($b['creances']))
        <p class="carte px-4 py-3 text-sm text-stone-500">Aucune créance : toutes les ventes de l’exercice étaient soldées à cette date.</p>
    @else
        <div class="carte overflow-x-auto">
            <table class="tableau"><thead class="bg-stone-50"><tr><th>N°</th><th>Date</th><th>Client</th><th>Produit</th><th class="text-right">Total</th><th class="text-right">Dû</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($b['creances'] as $c)
                        <tr><td class="tabular-nums">{{ $c['id'] }}</td><td class="tabular-nums">{{ Format::date($c['date']) }}</td><td>{{ $c['client'] }}</td><td>{{ $c['produit'] }}</td><td class="text-right tabular-nums">{{ Montant::fcfa($c['total']) }}</td><td class="text-right font-semibold tabular-nums">{{ Montant::fcfa($c['creance']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@if ($b['annexe_commandes']['n'])
    <p class="mt-4 text-xs text-stone-500">Annexe : {{ $b['annexe_commandes']['n'] }} commande(s) de l’ancien module, {{ Montant::fcfa($b['annexe_commandes']['total']) }}, présentée(s) pour mémoire et hors totaux.</p>
@endif

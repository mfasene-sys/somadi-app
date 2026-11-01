@php
    use App\Support\Montant;
    $noms = [1 => 'Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
    $max = max(1, collect($r['mois'])->flatMap(fn ($m) => [$m['recette'], $m['depense']])->max());
    $totR = collect($r['mois'])->sum('recette'); $totD = collect($r['mois'])->sum('depense');
@endphp
<x-layouts.app titre="Récapitulatif" :sous-titre="'Recettes et dépenses valides, année '.$annee">
    @include('commercial.tresorerie._onglets')

    <form method="GET" class="mb-4 flex items-end gap-2">
        <a href="?annee={{ $annee - 1 }}" class="btn-secondaire" aria-label="Année précédente">‹ {{ $annee - 1 }}</a>
        <span class="px-3 py-2 text-lg font-bold tabular-nums">{{ $annee }}</span>
        @if ($annee < today()->year)<a href="?annee={{ $annee + 1 }}" class="btn-secondaire">{{ $annee + 1 }} ›</a>@endif
    </form>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 [&>*:last-child]:col-span-2 sm:[&>*:last-child]:col-span-1">
        <x-indicateur label="Recettes" :valeur="Montant::fcfa($totR)"/>
        <x-indicateur label="Dépenses" :valeur="Montant::fcfa($totD)"/>
        <x-indicateur label="Solde" :valeur="Montant::fcfa($totR - $totD)" :ton="$totR < $totD ? 'alerte' : null"/>
    </div>

    <section class="carte mb-6 p-4">
        <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-stone-600">
            <h2 class="mr-auto text-sm font-semibold text-stone-900">Par mois</h2>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-ferme-600"></span>Recettes</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-paille-500"></span>Dépenses</span>
        </div>
        <div class="space-y-2.5">
            @foreach ($r['mois'] as $m => $v)
                <div class="grid grid-cols-[3rem_1fr_auto] items-center gap-3 text-sm" title="{{ $noms[$m] }} : recettes {{ Montant::fcfa($v['recette']) }}, dépenses {{ Montant::fcfa($v['depense']) }}">
                    <span class="text-stone-500">{{ $noms[$m] }}</span>
                    <div class="space-y-0.5">
                        <div class="h-2.5 rounded-r bg-ferme-600" style="width: {{ round($v['recette'] / $max * 100, 1) }}%"></div>
                        <div class="h-2.5 rounded-r bg-paille-500" style="width: {{ round($v['depense'] / $max * 100, 1) }}%"></div>
                    </div>
                    <span class="text-right text-xs tabular-nums {{ $v['recette'] - $v['depense'] < 0 ? 'text-red-700' : 'text-stone-700' }}">{{ $v['recette'] || $v['depense'] ? Montant::fcfa($v['recette'] - $v['depense']) : '—' }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 md:grid-cols-2">
        @foreach (['recette' => 'Recettes par catégorie', 'depense' => 'Dépenses par catégorie'] as $type => $titre)
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">{{ $titre }}</h2>
                <div class="carte divide-y divide-stone-100 text-sm">
                    @forelse (collect($r['categories'])->where('type', $type) as $c)
                        <div class="flex justify-between gap-3 px-4 py-2.5"><span>{{ $c['categorie'] }} <span class="text-xs text-stone-400">({{ $c['n'] }})</span></span><strong class="tabular-nums">{{ Montant::fcfa($c['total']) }}</strong></div>
                    @empty
                        <p class="px-4 py-3 text-stone-500">Aucune opération.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>

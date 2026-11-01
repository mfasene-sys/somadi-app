@php use App\Support\Format; use App\Domaine\Pesees; $nc = 'Non calculable'; @endphp
<x-layouts.app :titre="'Rentabilité · '.$lot->nom_lot" :sous-titre="ucfirst((string) $lot->espece).' · '.($age ?? '?').' jours · '.Format::nombre($r['restant']).' sujets restants'">
    <x-slot:actions><a href="{{ route('lots.show', $lot->id) }}" class="btn-secondaire">Fiche du lot</a></x-slot:actions>

    @unless ($r['complet'])
        <div class="mb-5 rounded-lg border border-paille-500/40 bg-paille-50 px-4 py-3 text-sm text-paille-700">
            <strong>Marge non certifiée.</strong>
            @if ($r['ages_sans_reference'])
                Aucun coût de référence pour l’âge de {{ implode(', ', array_map(fn ($a) => $a.' j', $r['ages_sans_reference'])) }} : ces sujets comptent pour un coût nul.
            @endif
            @unless ($r['ref_cible'])
                Aucune référence de coût jusqu’à l’âge cible ({{ $r['age_cible'] }} j).
            @endunless
            Complétez les <a href="{{ route('references.index') }}" class="underline">coûts de référence</a>.
        </div>
    @endunless

    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-stone-600">Hypothèse de vente dans</span>
        <div class="inline-flex flex-wrap rounded-lg border border-stone-300 p-0.5">
            @foreach (Pesees::HYPOTHESES as $h)
                <button name="hypothese" value="{{ $h }}" class="rounded-md px-3 py-1.5 font-medium {{ $hypothese === $h ? 'bg-ferme-700 text-white' : 'text-stone-600 hover:bg-stone-100' }}">{{ $h }} j</button>
            @endforeach
        </div>
        <span class="text-stone-500">soit à {{ $r['age_cible'] }} jours</span>
    </form>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-indicateur label="Ventes facturées" :valeur="Format::fcfa($r['ventes_facturees'])" :detail="$r['vendus'].' sujets vendus'"/>
        <x-indicateur label="Encaissé" :valeur="Format::fcfa($r['encaisse'])" detail="recettes comptables valides liées au lot"/>
        <x-indicateur label="Profit réalisé" :valeur="$r['complet'] ? Format::fcfa($r['profit_realise']) : $nc" :ton="$r['complet'] && $r['profit_realise'] < 0 ? 'alerte' : null" detail="ventes − coûts des vendus et des morts"/>
        <x-indicateur label="Profit projeté" :valeur="$r['complet'] && $r['profit_projete'] !== null ? Format::fcfa($r['profit_projete']) : $nc" :detail="'si vente des restants au prix minimal à '.$r['age_cible'].' j'"/>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="carte p-4">
            <h2 class="mb-3 font-semibold text-stone-900">Détail</h2>
            <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm">
                <dt class="text-stone-500">Coût de référence des vendus</dt><dd class="text-right tabular-nums">{{ Format::fcfa($r['couts_vendus']) }}</dd>
                <dt class="text-stone-500">Coût de référence des morts ({{ $r['morts'] }})</dt><dd class="text-right tabular-nums">{{ Format::fcfa($r['couts_morts']) }}</dd>
                <dt class="text-stone-500">Coût cible par tête ({{ $r['ref_cible']->age_jours ?? '—' }} j)</dt><dd class="text-right tabular-nums">{{ Format::fcfa($r['ref_cible']->cout_cumule_par_tete ?? null) }}</dd>
                <dt class="text-stone-500">Prix minimal profitable</dt><dd class="text-right tabular-nums">{{ Format::fcfa($r['ref_cible']->prix_min_profitable ?? null) }}</dd>
                <dt class="text-stone-500">Prix requis sur les restants</dt><dd class="text-right font-semibold tabular-nums">{{ $r['complet'] && $r['prix_requis'] !== null ? Format::fcfa($r['prix_requis']) : $nc }}</dd>
            </dl>
        </div>
        <div class="carte p-4">
            <h2 class="mb-3 font-semibold text-stone-900">Effectifs</h2>
            <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm">
                <dt class="text-stone-500">Initial</dt><dd class="text-right tabular-nums">{{ Format::nombre($lot->nombre_initial) }}</dd>
                <dt class="text-stone-500">Vendus · morts · restants</dt><dd class="text-right tabular-nums">{{ $r['vendus'] }} · {{ $r['morts'] }} · {{ $r['restant'] }}</dd>
                <dt class="text-stone-500">Mortalité</dt><dd class="text-right tabular-nums {{ $r['mortalite_pct'] > Pesees::SEUIL_MORTALITE ? 'font-semibold text-red-700' : '' }}">{{ Format::pourcent($r['mortalite_pct'], 1) }}</dd>
                <dt class="text-stone-500">Écart (initial − vendus − morts − restants)</dt><dd class="text-right tabular-nums">{{ $r['ecart'] }}</dd>
            </dl>
            <p class="mt-2 text-xs text-stone-500">L’écart n’intègre pas les entrées ni les sorties diverses.</p>
        </div>
    </div>

    @can('POI.gerer')
        <form method="POST" action="{{ route('rentabilite.exclusion', $lot->id) }}" class="carte mt-6 flex flex-wrap items-end gap-3 p-4">
            @csrf
            <input type="hidden" name="exclu" value="{{ ($option->exclu_analyses ?? false) ? 0 : 1 }}">
            <div class="min-w-0 flex-1">
                <div class="font-medium text-stone-900">{{ ($option->exclu_analyses ?? false) ? 'Ce lot est exclu des analyses' : 'Exclure ce lot des analyses' }}</div>
                <div class="text-xs text-stone-500">{{ $option->motif_exclusion ?? 'Par exemple un lot test ou atypique.' }}</div>
            </div>
            @unless ($option->exclu_analyses ?? false)<input name="motif" placeholder="Motif" maxlength="255" class="champ sm:w-64">@endunless
            <button class="btn-secondaire">{{ ($option->exclu_analyses ?? false) ? 'Réintégrer' : 'Exclure' }}</button>
        </form>
    @endcan
</x-layouts.app>

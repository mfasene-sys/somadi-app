@php use App\Support\Format; use App\Support\Montant; @endphp
<x-layouts.app titre="Retenues sur dépassement" :sous-titre="'Congés pris au-delà des droits '.$annee.' (salaire ÷ 30 par jour)'">
    <x-slot:actions><a href="?annee={{ $annee - 1 }}" class="btn-secondaire">‹ {{ $annee - 1 }}</a><a href="?annee={{ $annee + 1 }}" class="btn-secondaire">{{ $annee + 1 }} ›</a></x-slot:actions>
    @include('organisation.rh._onglets')
    <section class="mb-6">
        <h2 class="mb-3 font-semibold text-stone-900">À générer</h2>
        @if (! $apercu)
            <p class="carte px-4 py-3 text-sm text-stone-500">Aucun dépassement non traité.</p>
        @else
            <div class="carte divide-y divide-stone-100 text-sm">
                @foreach ($apercu as $r)<div class="flex gap-3 px-4 py-2.5"><span class="flex-1">{{ trim($r['employe']->prenom.' '.$r['employe']->nom) }}</span><span class="tabular-nums">{{ $r['jours'] }} j · <strong>{{ Montant::fcfa($r['montant']) }}</strong></span></div>@endforeach
            </div>
            @can('RH.gerer')
                <form method="POST" action="{{ route('rh.retenues.appliquer') }}" class="mt-3" onsubmit="return confirm('Générer ces retenues ?')">@csrf <input type="hidden" name="annee" value="{{ $annee }}">
                    <button class="btn-primaire">Générer les retenues (appliquées le mois prochain)</button></form>
            @endcan
            <p class="mt-2 text-xs text-stone-500">Seul le dépassement pas encore retenu est généré : relancer ne prélève pas deux fois. Ces montants sont une information de paie ; aucune opération de trésorerie n’est créée.</p>
        @endif
    </section>
    <section>
        <h2 class="mb-3 font-semibold text-stone-900">Retenues générées en {{ $annee }}</h2>
        <div class="carte divide-y divide-stone-100 text-sm">
            @forelse ($historique as $m)<div class="flex gap-3 px-4 py-2.5"><span class="w-20 tabular-nums text-stone-500">{{ Format::date($m->date_mvt) }}</span><span class="flex-1">{{ trim($m->prenom.' '.$m->nom) }}{{ $m->reference_table === 'correction' ? ' (correction)' : '' }}</span><span class="tabular-nums">{{ $m->jours }} j · {{ Montant::fcfa(Montant::base($m->montant)) }}</span></div>
            @empty <p class="px-4 py-3 text-stone-500">Aucune.</p> @endforelse
        </div>
    </section>
</x-layouts.app>

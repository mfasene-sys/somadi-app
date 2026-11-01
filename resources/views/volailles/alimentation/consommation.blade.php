@php use App\Support\Format; @endphp
<x-layouts.app titre="Consommation" :sous-titre="'Du '.Format::date($du).' au '.Format::date($au).' ('.$jours.' jours)'">
    @include('volailles.alimentation._onglets')
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-2">
        <x-champ name="du" label="Du" type="date" :value="$du"/>
        <x-champ name="au" label="Au" type="date" :value="$au"/>
        <button class="btn-secondaire">Afficher</button>
    </form>

    <div class="carte mb-6 overflow-x-auto">
        <table class="tableau">
            <thead class="bg-stone-50"><tr><th>Lot</th><th class="text-right">Effectif</th><th class="text-right">Consommé</th><th class="text-right">CMA</th><th class="text-right">Gain de poids</th><th class="text-right">Indice de consommation</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($lots as $x)
                    <tr>
                        <td class="font-medium"><a href="{{ route('lots.show', $x['lot']->id) }}" class="hover:underline">{{ $x['lot']->nom_lot }}</a></td>
                        <td class="text-right tabular-nums">{{ Format::nombre($x['c']['effectif']) }}</td>
                        <td class="text-right tabular-nums">{{ Format::nombre($x['c']['kg'], 1) }} kg</td>
                        <td class="text-right tabular-nums">{{ $x['c']['cma_g'] !== null ? Format::nombre($x['c']['cma_g'], 1).' g/sujet/j' : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $x['c']['gain_kg'] !== null ? Format::nombre($x['c']['gain_kg'], 3).' kg' : '—' }}</td>
                        <td class="text-right font-semibold tabular-nums">{{ $x['c']['indice'] !== null ? Format::nombre($x['c']['indice'], 2) : 'Indisponible' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-stone-500">Aucun lot actif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mb-6 text-xs text-stone-500">CMA = kg consommés ÷ (jours × effectif courant). Indice = kg consommés ÷ (gain de poids moyen en kg × effectif), calculé seulement avec au moins deux pesées et un gain positif sur la période.</p>

    <h2 class="mb-3 font-semibold text-stone-900">Par aliment</h2>
    <div class="grid gap-3 sm:grid-cols-3">
        @forelse ($parAliment as $a)
            <x-indicateur :label="$a->nom_aliment" :valeur="Format::kg($a->g, 1)"/>
        @empty
            <p class="text-sm text-stone-500">Aucune distribution sur la période.</p>
        @endforelse
    </div>
</x-layouts.app>

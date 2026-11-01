@php use App\Support\Format; @endphp
<x-layouts.app titre="Courbe de ponte" :sous-titre="'Du '.Format::date($du).' au '.Format::date($au)">
    <x-onglets :liens="['Saisie et validation' => route('ponte.index'), 'Courbe de ponte' => route('ponte.courbe')]"/>

    <form method="GET" class="carte mb-5 flex flex-wrap items-end gap-3 p-4">
        <x-choix name="bande" label="Bande" :value="$choix" class="min-w-56 flex-1 sm:flex-none"
                 :options="['toutes' => 'Toutes les bandes actives'] + $bandes->mapWithKeys(fn ($b) => [$b->id => $b->nom_bande.($b->reformee ? ' (réformée)' : '')])->all()" onchange="this.form.submit()"/>
        <div>
            <span class="etiquette">Période</span>
            <div class="inline-flex flex-wrap rounded-lg border border-stone-300 p-0.5">
                @foreach ([14 => '14 j', 30 => '30 j', 60 => '60 j', 90 => '90 j', 180 => '6 mois', 365 => '1 an'] as $j => $lib)
                    <button name="jours" value="{{ $j }}" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $jours === $j && ! request('du') ? 'bg-ferme-700 text-white' : 'text-stone-600 hover:bg-stone-100' }}">{{ $lib }}</button>
                @endforeach
            </div>
        </div>
    </form>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-indicateur label="Taux de ponte moyen" :valeur="Format::pourcent($synthese['taux_moyen'], 1)" detail="moyenne des jours saisis"/>
        <x-indicateur label="Œufs ramassés" :valeur="Format::nombre($synthese['oeufs'])"/>
        <x-indicateur label="Part de défectueux" :valeur="Format::pourcent($synthese['defect_pct'], 1)" detail="sur les ramassages validés"/>
        <x-indicateur label="Jours saisis" :valeur="$synthese['jours_saisis'].' / '.count($dates)" detail="un jour sans saisie n’est pas un jour sans ponte"/>
    </div>

    @if ($tronque)
        <p class="mb-3 text-sm text-paille-700">Plus de 8 bandes : seules les 8 premières sont tracées. Choisissez une bande pour la voir seule.</p>
    @endif

    <div class="space-y-5">
        <x-graphe-lignes titre="Taux de ponte par jour" :dates="$dates" :series="$series" :max="100" unite="%" :decimales="1">
            <p class="mt-2 text-xs text-stone-500">Taux = œufs ramassés ÷ femelles présentes le jour de la saisie. Les interruptions de la courbe correspondent aux jours sans saisie.</p>
        </x-graphe-lignes>
        <x-graphe-lignes titre="Qualité : part d’œufs défectueux" :dates="$dates" :series="$qualite" unite="%" :decimales="1">
            <p class="mt-2 text-xs text-stone-500">Défectueux ÷ œufs contrôlés, sur les seuls ramassages validés.</p>
        </x-graphe-lignes>
    </div>

    <details class="mt-5">
        <summary class="cursor-pointer text-sm font-medium text-ferme-700">Voir les valeurs</summary>
        <div class="carte mt-3 overflow-x-auto">
            <table class="tableau">
                <thead class="bg-stone-50"><tr><th>Date</th><th>Bande</th><th class="text-right">Œufs</th><th class="text-right">Taux</th><th class="text-right">Défectueux</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($lignes->sortByDesc('date') as $l)
                        <tr>
                            <td class="tabular-nums">{{ Format::date($l->date) }}</td>
                            <td>{{ $noms[$l->id_bande] ?? '#'.$l->id_bande }}</td>
                            <td class="text-right tabular-nums">{{ Format::nombre($l->total) }}</td>
                            <td class="text-right tabular-nums">{{ Format::pourcent($l->taux, 2) }}</td>
                            <td class="text-right tabular-nums">{{ (int) $l->controles > 0 ? $l->defect.' ('.Format::pourcent(100 * $l->defect / $l->controles, 1).')' : 'non validé' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-stone-500">Aucune saisie sur la période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
</x-layouts.app>

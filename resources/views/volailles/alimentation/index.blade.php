@php use App\Support\Format; @endphp
<x-layouts.app titre="Alimentation" :sous-titre="'Distributions du '.Format::dateLongue($date)">
    @include('volailles.alimentation._onglets')

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-2">
        <x-champ name="date" label="Date" type="date" :value="$date" :max="today()->toDateString()" onchange="this.form.submit()"/>
    </form>

    @if (session('ignores'))
        <details class="mb-5 rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm">
            <summary class="cursor-pointer text-stone-600">{{ count(session('ignores')) }} lot(s) sans distribution générée</summary>
            <ul class="mt-2 list-disc pl-5 text-stone-500">@foreach (session('ignores') as $i)<li>{{ $i }}</li>@endforeach</ul>
        </details>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-stone-900">Prévu par les plans</h2>
                    @can('ALI.distribuer')
                        <form method="POST" action="{{ route('alimentation.generer') }}">
                            @csrf <input type="hidden" name="date" value="{{ $date }}">
                            <button class="btn-primaire">Générer les distributions du jour</button>
                        </form>
                    @endcan
                </div>
                <p class="mb-3 text-xs text-stone-500">Semaine = âge en jours ÷ 7 + 1 ; au-delà du plan, sa dernière semaine s’applique. Une distribution déjà enregistrée n’est jamais remplacée par une nouvelle génération.</p>
                <div class="carte overflow-x-auto">
                    <table class="tableau">
                        <thead class="bg-stone-50"><tr><th>Lot</th><th class="text-right">Effectif</th><th class="text-right">Sem.</th><th>Besoin</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($previsions as $p)
                                <tr>
                                    <td class="font-medium">{{ $p['lot']->nom_lot }}</td>
                                    <td class="text-right tabular-nums">{{ Format::nombre($p['lot']->effectif_en_cours) }}</td>
                                    <td class="text-right tabular-nums">{{ $p['b']['semaine'] ?? '—' }}</td>
                                    <td>
                                        @forelse ($p['b']['besoins'] as $alim => $b)
                                            <span class="mr-2 whitespace-nowrap">{{ $aliments[$alim]->nom_aliment ?? '#'.$alim }} : <strong class="tabular-nums">{{ Format::kg($b['grammes']) }}</strong></span>
                                        @empty
                                            <span class="text-stone-400">{{ $p['b']['raison'] }}</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-stone-500">Aucun lot actif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Distributions enregistrées</h2>
                @if ($distributions->isEmpty())
                    <x-vide icone="wheat">Aucune distribution enregistrée pour cette date.</x-vide>
                @else
                    <div class="carte divide-y divide-stone-100">
                        @foreach ($distributions as $d)
                            <details class="group px-4 py-3">
                                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 text-sm">
                                    <span class="font-medium text-stone-900">{{ $d->nom_lot }}</span>
                                    <span class="text-stone-500">{{ $aliments[$d->id_type_aliment]->nom_aliment ?? '?' }}</span>
                                    <span class="ml-auto font-semibold tabular-nums">{{ Format::kg($d->quantite_totale) }}</span>
                                    <x-icone nom="chevron-right" class="size-4 text-stone-400 transition group-open:rotate-90"/>
                                </summary>
                                <div class="mt-2 space-y-3">
                                    <p class="whitespace-pre-line break-words text-xs text-stone-500">{{ $d->remarque }}</p>
                                    @can('ALI.gerer')
                                        <form method="POST" action="{{ route('alimentation.corriger', $d->id) }}" class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            <label class="text-xs text-stone-500">Quantité corrigée (kg)<input name="quantite_kg" inputmode="decimal" required class="champ mt-1 w-28" value="{{ str_replace('.', ',', round($d->quantite_totale / 1000, 2)) }}"></label>
                                            <label class="min-w-48 flex-1 text-xs text-stone-500">Motif<input name="motif" required maxlength="500" class="champ mt-1"></label>
                                            <button class="btn-secondaire">Corriger</button>
                                        </form>
                                    @endcan
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        @can('ALI.distribuer')
            <form method="POST" action="{{ route('alimentation.distribuer') }}" class="carte h-fit space-y-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Distribution manuelle</h2>
                <x-choix name="id_lot" label="Lot" :options="$lots->pluck('nom_lot', 'id')->all()" vide="Choisir…" required/>
                <x-choix name="id_type_aliment" label="Aliment" :options="$aliments->pluck('nom_aliment', 'id')->all()" vide="Choisir…" required/>
                <div class="grid grid-cols-2 gap-3">
                    <x-champ name="quantite_kg" label="Quantité" suffixe="kg" required inputmode="decimal" placeholder="12,5"/>
                    <x-champ name="date_distribution" label="Date" type="date" :value="$date" :max="today()->toDateString()" required/>
                </div>
                <x-champ name="remarque" label="Remarque" maxlength="500"/>
                <p class="text-xs text-stone-500">Si une distribution du même aliment existe déjà pour ce lot et cette date, la quantité s’y ajoute.</p>
                <button class="btn-primaire w-full">Enregistrer</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

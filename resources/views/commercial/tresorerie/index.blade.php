@php
    use App\Support\Montant; use App\Support\Format;
    $rec = Montant::base($totaux['recette'] ?? 0); $dep = Montant::base($totaux['depense'] ?? 0);
    $moisNoms = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $toutesCat = array_merge($categories['recette'], $categories['depense']);
@endphp
<x-layouts.app titre="Trésorerie" :sous-titre="'Journal des recettes et dépenses '.$annee">
    @include('commercial.tresorerie._onglets')

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 [&>*:last-child]:col-span-2 sm:[&>*:last-child]:col-span-1">
        <x-indicateur label="Recettes" :valeur="Montant::fcfa($rec)"/>
        <x-indicateur label="Dépenses" :valeur="Montant::fcfa($dep)"/>
        <x-indicateur label="Solde" :valeur="Montant::fcfa($rec - $dep)" :ton="$rec - $dep < 0 ? 'alerte' : null"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        @can('CPT.gerer')
            {{-- Repliée sur téléphone pour laisser voir le journal ; ouverte sur grand écran ou après une erreur. --}}
            <details class="carte group h-fit lg:order-2" data-ouvert-large @if ($errors->any()) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-2 p-4 font-semibold text-stone-900"><x-icone nom="plus" class="size-4"/> Nouvelle opération</summary>
            <form method="POST" action="{{ route('tresorerie.store') }}" class="space-y-3 px-4 pb-4">
                @csrf <x-cle/>
                <div class="inline-flex rounded-lg border border-stone-300 p-0.5">
                    @foreach (['depense' => 'Dépense', 'recette' => 'Recette'] as $k => $lib)
                        <label class="cursor-pointer"><input type="radio" name="type_op" value="{{ $k }}" class="peer sr-only" @checked(old('type_op', 'depense') === $k) onchange="majCategories(this.value)">
                            <span class="block rounded-md px-4 py-2 text-sm font-medium text-stone-600 peer-checked:bg-ferme-700 peer-checked:text-white">{{ $lib }}</span></label>
                    @endforeach
                </div>
                <x-champ name="date_op" label="Date" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                <x-choix name="categorie" label="Catégorie" :options="array_combine($categories[old('type_op', 'depense')], $categories[old('type_op', 'depense')])" required/>
                <x-champ name="libelle" label="Libellé" required maxlength="255"/>
                <x-champ name="montant" label="Montant" suffixe="FCFA" required inputmode="decimal"/>
                <x-choix name="moyen_paiement" label="Moyen de paiement" :options="array_combine($moyens, $moyens)" vide="Choisir…" required/>
                <x-champ name="observations" label="Observations" type="textarea"/>
                <p class="text-xs text-stone-500">Les paiements de ventes s’enregistrent depuis la fiche de la vente, pas ici.</p>
                <button class="btn-primaire w-full">Enregistrer</button>
            </form>
            </details>
            <script>
                if (window.matchMedia('(min-width: 1024px)').matches) document.querySelectorAll('[data-ouvert-large]').forEach(function (d) { d.open = true; });
                var SOMADI_CAT = @json($categories);
                function majCategories(t) {
                    var s = document.getElementById('categorie'); s.innerHTML = '';
                    SOMADI_CAT[t].forEach(function (c) { var o = document.createElement('option'); o.value = o.textContent = c; s.appendChild(o); });
                }
            </script>
        @endcan

        <div class="min-w-0 lg:order-1">
            <form method="GET" class="carte mb-4 grid grid-cols-2 gap-3 p-4 sm:grid-cols-5">
                <x-champ name="annee" label="Année" type="number" :value="$annee" min="2000" max="2099"/>
                <x-choix name="mois" label="Mois" :options="$moisNoms" :value="$filtres['mois'] ?? null" vide="Tous"/>
                <x-choix name="type" label="Type" :options="['recette' => 'Recettes', 'depense' => 'Dépenses']" :value="$filtres['type'] ?? null" vide="Tous"/>
                <x-choix name="categorie" label="Catégorie" :options="array_combine($toutesCat, $toutesCat)" :value="$filtres['categorie'] ?? null" vide="Toutes" id="filtre_categorie"/>
                <div class="col-span-2 flex items-end gap-3 sm:col-span-1 sm:flex-col sm:items-stretch">
                    <label class="flex items-center gap-2 text-xs text-stone-600"><input type="checkbox" name="invalides" value="1" class="size-4" @checked(! empty($filtres['invalides']))> Invalidées</label>
                    <button class="btn-secondaire flex-1">Filtrer</button>
                </div>
            </form>

            @if ($operations->isEmpty())
                <x-vide icone="wallet">Aucune opération sur cette période.</x-vide>
            @else
                {{-- Téléphone : une carte par opération --}}
                <div class="carte divide-y divide-stone-100 md:hidden">
                    @foreach ($operations as $o)
                        <div class="px-4 py-3 text-sm {{ $o->est_valide ? '' : 'text-stone-400' }}">
                            <div class="flex items-start gap-3">
                                <span class="min-w-0 flex-1">
                                    @if ($o->source_table === \App\Domaine\Tresorerie::SOURCE_VENTE && $o->source_id)
                                        <a href="{{ route('ventes.show', $o->source_id) }}" class="font-medium text-ferme-700">{{ $o->libelle }}</a>
                                    @else <span class="font-medium text-stone-900">{{ $o->libelle }}</span> @endif
                                    <span class="block text-xs text-stone-500">{{ Format::date($o->date_op) }} · {{ $o->categorie }} · {{ $o->moyen_paiement }}</span>
                                    @unless ($o->est_valide)<span class="block text-xs">Invalidée : {{ $o->invalidated_comment }}</span>@endunless
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums {{ $o->est_valide ? ($o->type_op === 'recette' ? 'text-ferme-700' : 'text-stone-900') : 'line-through' }}">{{ $o->type_op === 'recette' ? '+' : '−' }} {{ Montant::fcfa(Montant::base($o->montant)) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="carte hidden overflow-x-auto md:block">
                    <table class="tableau">
                        <thead class="bg-stone-50"><tr><th>Date</th><th>Libellé</th><th>Catégorie</th><th>Moyen</th><th class="text-right">Montant</th><th></th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($operations as $o)
                                <tr class="{{ $o->est_valide ? '' : 'text-stone-400' }}">
                                    <td class="tabular-nums">{{ Format::date($o->date_op) }}</td>
                                    <td class="whitespace-normal">
                                        @if ($o->source_table === \App\Domaine\Tresorerie::SOURCE_VENTE && $o->source_id)
                                            <a href="{{ route('ventes.show', $o->source_id) }}" class="text-ferme-700 hover:underline">{{ $o->libelle }}</a>
                                        @else {{ $o->libelle }} @endif
                                        @unless ($o->est_valide)<span class="block text-xs">Invalidée : {{ $o->invalidated_comment }}</span>@endunless
                                    </td>
                                    <td>{{ $o->categorie }}</td>
                                    <td>{{ $o->moyen_paiement }}</td>
                                    <td class="text-right font-semibold tabular-nums {{ $o->est_valide ? ($o->type_op === 'recette' ? 'text-ferme-700' : 'text-stone-900') : 'line-through' }}">{{ $o->type_op === 'recette' ? '+' : '−' }} {{ Montant::fcfa(Montant::base($o->montant)) }}</td>
                                    <td>
                                        @if ($o->est_valide)
                                            @can('CPT.gerer')
                                                <details>
                                                    <summary class="cursor-pointer text-xs font-medium text-red-700">Invalider</summary>
                                                    <form method="POST" action="{{ route('tresorerie.invalider', $o->id) }}" class="mt-2 flex w-64 flex-col gap-2">
                                                        @csrf
                                                        <input name="motif" required minlength="3" maxlength="1000" placeholder="Motif" class="champ">
                                                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="confirmation" value="1" required class="size-4"> Je confirme</label>
                                                        <button class="btn-secondaire min-h-9 text-xs">Invalider</button>
                                                    </form>
                                                </details>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $operations->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.app>

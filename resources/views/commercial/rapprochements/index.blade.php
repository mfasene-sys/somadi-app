@php use App\Support\Montant; use App\Support\Format; @endphp
<x-layouts.app titre="Rapprochements" sous-titre="Relier aux ventes les recettes saisies sans lien et corriger le reste à encaisser">
    <form method="GET" class="carte mb-4 grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
        <x-champ name="annee" label="Année" type="number" :value="$annee" min="2000" max="2099"/>
        <x-champ name="q" label="Client ou téléphone" :value="$filtres['q'] ?? ''"/>
        <label class="flex items-end gap-2 pb-3 text-sm text-stone-600"><input type="checkbox" name="tous" value="1" class="size-4" @checked(! empty($filtres['tous']))> Toutes les ventes</label>
        <div class="flex items-end"><button class="btn-secondaire w-full">Filtrer</button></div>
    </form>
    <p class="mb-3 text-sm text-stone-500">{{ empty($filtres['tous']) ? 'Ventes dont le reste à encaisser ne correspond pas aux règlements liés.' : 'Toutes les ventes de l’année.' }}</p>

    @if ($ventes->isEmpty())
        <x-vide icone="check">Aucun écart : chaque vente est cohérente avec ses règlements.</x-vide>
    @else
        <div class="carte overflow-x-auto">
            <table class="tableau">
                <thead class="bg-stone-50"><tr><th>N°</th><th>Date</th><th>Client</th><th class="text-right">Total</th><th class="text-right">Réglé (lié)</th><th class="text-right">Reste affiché</th><th>Écart</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($ventes as $v)
                        @php($ecart = Montant::base($v->montant_total) - Montant::base($v->paye) - Montant::base($v->montant_reste_encaisser))
                        <tr class="cursor-pointer hover:bg-stone-50" onclick="location.href='{{ route('rapprochements.show', $v->id) }}'">
                            <td class="tabular-nums"><a href="{{ route('rapprochements.show', $v->id) }}" class="text-ferme-700">{{ $v->id }}</a></td>
                            <td class="tabular-nums">{{ Format::date($v->date_vente) }}</td>
                            <td>{{ $v->client_nom }}</td>
                            <td class="text-right tabular-nums">{{ Montant::fcfa(Montant::base($v->montant_total)) }}</td>
                            <td class="text-right tabular-nums">{{ Montant::fcfa(Montant::base($v->paye)) }}</td>
                            <td class="text-right tabular-nums">{{ Montant::fcfa(Montant::base($v->montant_reste_encaisser)) }}</td>
                            <td>
                                @if ($v->depenses > 0)<x-statut ton="alerte">Dépense liée</x-statut>
                                @elseif ($ecart !== 0)<x-statut ton="attente">{{ Montant::fcfa($ecart) }}</x-statut>
                                @else <x-statut ton="ok">Cohérent</x-statut> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $ventes->links() }}</div>
    @endif

    @if ($journal->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 font-semibold text-stone-900">Derniers rapprochements</h2>
            <div class="carte divide-y divide-stone-100 text-sm">
                @foreach ($journal as $j)
                    <a href="{{ route('rapprochements.show', $j->id_vente) }}" class="flex gap-3 px-4 py-2.5 hover:bg-stone-50">
                        <span class="w-24 shrink-0 tabular-nums text-stone-500">{{ Format::date(substr($j->created_at, 0, 10)) }}</span>
                        <span class="flex-1 truncate">Vente n° {{ $j->id_vente }} · {{ $j->motif }}</span>
                        <span class="text-stone-500">{{ $j->author_name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>

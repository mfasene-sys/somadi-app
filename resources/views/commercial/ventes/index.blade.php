@php use App\Support\Montant; use App\Support\Format; use App\Domaine\Ventes; @endphp
<x-layouts.app titre="Ventes" :sous-titre="'Registre '.$annee">
    @include('commercial.ventes._onglets')

    @if ($aCompleter)
        <a href="{{ route('ventes.a_completer') }}" class="mb-4 flex items-center gap-3 rounded-lg border border-paille-500/40 bg-paille-50 px-4 py-3 text-sm text-paille-700 hover:bg-paille-100">
            <x-icone nom="clock" class="size-5"/><span class="flex-1"><strong>{{ $aCompleter }}</strong> sortie(s) de volailles attendent d’être finalisées en vente.</span><x-icone nom="chevron-right" class="size-4"/>
        </a>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 [&>*:last-child]:col-span-2 sm:[&>*:last-child]:col-span-1">
        <x-indicateur label="Ventes" :valeur="Format::nombre($totaux->n)"/>
        <x-indicateur label="Montant" :valeur="Montant::fcfa(Montant::base($totaux->total))"/>
        <x-indicateur label="Reste à encaisser" :valeur="Montant::fcfa(Montant::base($totaux->reste))" :ton="Montant::base($totaux->reste) > 0 ? 'alerte' : null"/>
    </div>

    <form method="GET" class="carte mb-4 grid gap-3 p-4 sm:grid-cols-5">
        <x-choix name="annee" label="Année" :options="$annees->mapWithKeys(fn ($a) => [$a => $a])->all()" :value="$annee"/>
        <x-champ name="q" label="Client ou téléphone" :value="$filtres['q'] ?? ''" class="sm:col-span-2"/>
        <x-choix name="statut" label="Statut" :options="['A encaisser' => 'À encaisser', 'Encaissé' => 'Encaissé']" :value="$filtres['statut'] ?? null" vide="Tous"/>
        <div class="flex items-end"><button class="btn-secondaire w-full">Filtrer</button></div>
    </form>

    @if ($ventes->isEmpty())
        <x-vide icone="cart">Aucune vente.</x-vide>
    @else
        <div class="carte overflow-x-auto">
            <table class="tableau">
                <thead class="bg-stone-50"><tr><th>N°</th><th>Date</th><th>Client</th><th>Produit</th><th class="text-right">Qté</th><th class="text-right">Total</th><th class="text-right">Reste</th><th>Statut</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($ventes as $v)
                        <tr class="cursor-pointer hover:bg-stone-50" onclick="location.href='{{ route('ventes.show', $v->id) }}'">
                            <td class="tabular-nums text-stone-500"><a href="{{ route('ventes.show', $v->id) }}">{{ $v->id }}</a></td>
                            <td class="tabular-nums">{{ Format::date($v->date_vente) }}</td>
                            <td class="font-medium">{{ $v->client_nom }}<span class="block text-xs font-normal text-stone-500">{{ $v->client_tel }}</span></td>
                            <td>{{ Ventes::libelle($v->espece) }}</td>
                            <td class="text-right tabular-nums">{{ Format::nombre($v->quantite) }}</td>
                            <td class="text-right tabular-nums">{{ Montant::fcfa(Montant::base($v->montant_total)) }}</td>
                            <td class="text-right tabular-nums {{ Montant::base($v->montant_reste_encaisser) > 0 ? 'font-semibold' : 'text-stone-400' }}">{{ Montant::fcfa(Montant::base($v->montant_reste_encaisser)) }}</td>
                            <td><x-statut :ton="$v->statut === 'Encaissé' ? 'ok' : 'attente'">{{ $v->statut === 'Encaissé' ? 'Encaissé' : 'À encaisser' }}</x-statut></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $ventes->links() }}</div>
    @endif
</x-layouts.app>

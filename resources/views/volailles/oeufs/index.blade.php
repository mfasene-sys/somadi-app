@php use App\Support\Format; use App\Support\Empreinte; use App\Domaine\StockOeufs; @endphp
<x-layouts.app titre="Stock des œufs" sous-titre="Œufs validés à répartir entre incubation et vente">
    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <x-indicateur label="Œufs en stock à répartir" :valeur="Format::nombre($stocks->sum('total_oeufs'))" :detail="$stocks->count().' journée(s)'"/>
        <x-indicateur label="Lots « À incuber » non chargés" :valeur="Format::nombre($totaux['incuber'] ?? 0)"/>
        <x-indicateur label="Lots « À vendre » disponibles" :valeur="Format::nombre($totaux['vendre'] ?? 0)"/>
    </div>

    <section class="mb-8">
        <h2 class="mb-3 font-semibold text-stone-900">Stock à répartir</h2>
        @if ($stocks->isEmpty())
            <x-vide icone="egg">Aucun œuf validé en attente de répartition.</x-vide>
        @else
            <div class="space-y-2">
                @foreach ($stocks as $s)
                    <div class="carte p-4">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-stone-900">{{ $s->nom_bande }} · ramassage du {{ Format::date($s->date_ramassage) }}</div>
                                <div class="text-xs text-stone-500">{{ ucfirst($s->espece) }} · {{ $s->audit['valides'] ?? '?' }} œufs validés, {{ $s->audit['affectes'] }} déjà affectés</div>
                            </div>
                            <div class="text-right"><span class="text-2xl font-bold tabular-nums">{{ $s->total_oeufs }}</span> <span class="text-sm text-stone-500">restants</span></div>
                        </div>
                        @if (! $s->audit['ok'])
                            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">État incohérent à examiner : {{ $s->audit['message'] }}</p>
                        @else
                            @can('OEU.gerer')
                                <form method="POST" action="{{ route('oeufs.affecter', $s->id) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-stone-100 pt-3">
                                    @csrf
                                    <input type="hidden" name="empreinte" value="{{ Empreinte::de(\Illuminate\Support\Arr::except((array) $s, ['nom_bande', 'espece', 'audit'])) }}">
                                    <div class="inline-flex rounded-lg border border-stone-300 p-0.5" role="radiogroup" aria-label="Destination">
                                        @foreach (StockOeufs::TYPES as $k => $v)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="type_lot" value="{{ $k }}" class="peer sr-only" @checked($loop->first) required>
                                                <span class="block rounded-md px-3 py-2 text-sm font-medium text-stone-600 peer-checked:bg-ferme-700 peer-checked:text-white">{{ $v }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <input type="number" name="quantite" min="1" max="{{ $s->total_oeufs }}" value="{{ $s->total_oeufs }}" required inputmode="numeric" class="champ w-28" aria-label="Quantité">
                                    <button type="submit" class="btn-primaire">Affecter</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <h2 class="font-semibold text-stone-900">Lots d’œufs</h2>
            <form method="GET" class="flex flex-wrap gap-2">
                <select name="type" class="champ w-auto" onchange="this.form.submit()" aria-label="Destination">
                    <option value="">Toutes destinations</option>
                    @foreach (StockOeufs::TYPES as $k => $v)<option value="{{ $k }}" @selected(($filtres['type'] ?? '') === $k)>{{ $v }}</option>@endforeach
                </select>
                <select name="statut" class="champ w-auto" onchange="this.form.submit()" aria-label="Statut">
                    @foreach (['nouveau' => 'Disponibles', 'charge' => 'Chargés', 'vendu' => 'Vendus'] as $k => $v)<option value="{{ $k }}" @selected($filtres['statut'] === $k)>{{ $v }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="carte overflow-x-auto">
            <table class="tableau">
                <thead class="bg-stone-50"><tr><th>N°</th><th>Créé le</th><th>Bande · ramassage</th><th>Destination</th><th class="text-right">Quantité</th><th>Statut</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($lots as $l)
                        <tr>
                            <td class="tabular-nums text-stone-500">{{ $l->id }}</td>
                            <td class="tabular-nums">{{ Format::date($l->date_creation) }}</td>
                            <td>{{ $l->nom_bande }} · {{ Format::date($l->date_ramassage) }}</td>
                            <td><x-statut :ton="$l->type_lot === 'incuber' ? 'info' : 'attente'">{{ StockOeufs::TYPES[$l->type_lot] ?? $l->type_lot }}</x-statut></td>
                            <td class="text-right font-semibold tabular-nums">{{ $l->quantite }}</td>
                            <td>{{ ['nouveau' => 'Disponible', 'charge' => 'Chargé', 'vendu' => 'Vendu'][$l->statut] ?? $l->statut }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-stone-500">Aucun lot.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $lots->links() }}</div>
    </section>
</x-layouts.app>

@php use App\Support\Format; use App\Domaine\Ventes; $produits = collect(Ventes::produits())->only(['oeufs_feconds', 'oeufs_table', 'oeufs_caille']); @endphp
<x-layouts.app titre="Vente d’œufs" sous-titre="Les œufs sont prélevés sur les lots sélectionnés, du plus ancien au plus récent">
    @include('commercial.ventes._onglets')

    <form method="GET" class="mb-4 flex flex-wrap gap-1 rounded-xl bg-stone-100 p-1 sm:w-fit">
        @foreach ($produits as $k => $p)
            <button name="produit" value="{{ $k }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ $produit === $k ? 'bg-white text-ferme-800 shadow-sm' : 'text-stone-600' }}">{{ $p['libelle'] }}</button>
        @endforeach
    </form>

    @if ($lots->isEmpty())
        <x-vide icone="egg">Aucun lot « À vendre » disponible pour ce produit. Affectez des œufs « À vendre » depuis le stock.</x-vide>
    @else
        <form method="POST" action="{{ route('ventes.oeufs.store') }}" class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            @csrf <x-cle/>
            <input type="hidden" name="produit" value="{{ $produit }}">
            <div class="carte divide-y divide-stone-100">
                @foreach ($lots as $l)
                    <label class="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2 text-sm hover:bg-stone-50 {{ $l->dispo['coherent'] ? '' : 'opacity-60' }}">
                        <input type="checkbox" name="lots[]" value="{{ $l->id }}" class="size-5" @checked(in_array($l->id, old('lots', [])) || ! old('lots')) @disabled(! $l->dispo['coherent']) data-dispo="{{ $l->dispo['disponible'] }}" onchange="majTotal()">
                        <span class="flex-1">Lot n° {{ $l->id }} · {{ $l->nom_bande }} · ramassé le {{ Format::date($l->date_ramassage) }}
                            @unless ($l->dispo['coherent'])<span class="block text-xs text-red-700">{{ $l->dispo['message'] }}</span>@endunless
                        </span>
                        <span class="text-right tabular-nums"><strong>{{ $l->dispo['disponible'] }}</strong><span class="block text-xs text-stone-500">sur {{ $l->quantite }}</span></span>
                    </label>
                @endforeach
            </div>
            <div class="carte h-fit space-y-4 p-4">
                <p class="text-sm text-stone-600">Disponible sélectionné : <strong id="total-dispo" class="tabular-nums">0</strong> œufs</p>
                <x-champ name="quantite" label="Quantité vendue" type="number" min="1" required inputmode="numeric"/>
                <x-champ name="prix_unitaire" label="Prix par œuf" suffixe="FCFA" required inputmode="decimal"/>
                <x-champ name="client_nom" label="Client" required maxlength="100"/>
                <x-champ name="client_tel" label="Téléphone" type="tel" required inputmode="tel"/>
                <x-champ name="date_vente" label="Date" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                @include('commercial._encaissement-immediat')
                <button class="btn-primaire w-full">Enregistrer la vente</button>
            </div>
        </form>
        <script>
            function majTotal() {
                var t = 0; document.querySelectorAll('input[name="lots[]"]:checked').forEach(function (c) { t += parseInt(c.dataset.dispo || 0, 10); });
                document.getElementById('total-dispo').textContent = t.toLocaleString('fr-FR');
                var q = document.getElementById('quantite'); if (q) q.max = t;
            }
            majTotal();
        </script>
    @endif
</x-layouts.app>

@php use App\Support\Format; @endphp
<x-layouts.app titre="Stocks d’aliments" sous-titre="Entrées en kg, consommations déduites des distributions">
    @include('volailles.alimentation._onglets')

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($stocks as $s)
                    <div class="carte p-4 {{ $s->alerte ? 'border-red-200' : '' }}">
                        <div class="flex items-center justify-between"><span class="font-medium text-stone-900">{{ $s->nom_aliment }}</span>@if ($s->alerte)<x-statut ton="alerte">Sous le seuil</x-statut>@endif</div>
                        <div class="mt-2 text-2xl font-bold tabular-nums {{ $s->disponible_kg < 0 ? 'text-red-700' : '' }}">{{ Format::nombre($s->disponible_kg, 1) }} kg</div>
                        <div class="text-xs text-stone-500">entrées {{ Format::nombre($s->entrees_kg, 1) }} kg · distribué {{ Format::nombre($s->sorties_kg, 1) }} kg @if ($s->seuil_kg !== null)· seuil {{ Format::nombre($s->seuil_kg, 0) }} kg @endif</div>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Aucun aliment déclaré.</p>
                @endforelse
            </div>

            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Dernières entrées</h2>
                <div class="carte overflow-x-auto">
                    <table class="tableau">
                        <thead class="bg-stone-50"><tr><th>Date</th><th>Aliment</th><th class="text-right">Quantité</th><th class="text-right">Seuil</th><th>Commentaire</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($entrees as $e)
                                <tr><td class="tabular-nums">{{ Format::date($e->date_entree) }}</td><td>{{ $e->nom_aliment }}</td><td class="text-right tabular-nums">{{ Format::nombre($e->quantite_kg, 2) }} kg</td><td class="text-right tabular-nums">{{ $e->seuil_alerte !== null ? Format::nombre($e->seuil_alerte, 0).' kg' : '—' }}</td><td class="text-stone-500">{{ $e->commentaire }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-stone-500">Aucune entrée.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        @can('ALI.gerer')
            <div class="space-y-5">
                <form method="POST" action="{{ route('alimentation.entree') }}" class="carte space-y-3 p-4">
                    @csrf
                    <h2 class="font-semibold text-stone-900">Entrée de stock</h2>
                    <x-choix name="id_type" label="Aliment" :options="$stocks->pluck('nom_aliment', 'id')->all()" vide="Choisir…" required/>
                    <div class="grid grid-cols-2 gap-3">
                        <x-champ name="quantite_kg" label="Quantité" type="number" step="0.01" min="0.01" suffixe="kg" required/>
                        <x-champ name="date_entree" label="Date" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                    </div>
                    <x-champ name="seuil_alerte" label="Seuil d’alerte" type="number" step="0.01" min="0" suffixe="kg"/>
                    <x-champ name="commentaire" label="Commentaire" maxlength="500"/>
                    <button class="btn-primaire w-full">Enregistrer l’entrée</button>
                </form>
                <form method="POST" action="{{ route('alimentation.aliment') }}" class="carte space-y-3 p-4">
                    @csrf
                    <h2 class="font-semibold text-stone-900">Nouvel aliment</h2>
                    <x-champ name="nom_aliment" label="Nom" maxlength="100" required/>
                    <button class="btn-secondaire w-full">Ajouter</button>
                </form>
            </div>
        @endcan
    </div>
</x-layouts.app>

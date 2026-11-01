@php use App\Support\Format; @endphp
<x-layouts.app titre="Coûts de référence" sous-titre="Coût cumulé par tête selon l’âge, et marge cible">
    <x-onglets :liens="['Lots' => route('rentabilite.index'), 'Coûts de référence' => route('references.index')]"/>
    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="carte overflow-x-auto">
            <table class="tableau">
                <thead class="bg-stone-50"><tr><th>Espèce</th><th class="text-right">Âge</th><th class="text-right">Coût cumulé</th><th class="text-right">Marge</th><th class="text-right">Prix minimal</th><th>Active</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($refs as $r)
                        <tr class="{{ $r->actif ? '' : 'text-stone-400' }}">
                            <td>{{ $r->espece }}</td><td class="text-right tabular-nums">{{ $r->age_jours }} j</td>
                            <td class="text-right tabular-nums">{{ Format::fcfa($r->cout_cumule_par_tete) }}</td>
                            <td class="text-right tabular-nums">{{ Format::pourcent($r->marge_pct, 0) }}</td>
                            <td class="text-right font-medium tabular-nums">{{ Format::fcfa($r->prix_min_profitable) }}</td>
                            <td>{{ $r->actif ? 'Oui' : 'Non' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-stone-500">Aucune référence.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('POI.gerer')
            <form method="POST" action="{{ route('references.store') }}" class="carte space-y-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Ajouter ou mettre à jour</h2>
                <p class="text-xs text-stone-500">Une référence existante (même espèce, même âge) est remplacée.</p>
                <div class="grid grid-cols-2 gap-3">
                    <x-champ name="espece" label="Espèce" value="poulet" required/>
                    <x-champ name="age_jours" label="Âge" type="number" min="0" suffixe="j" required/>
                </div>
                <x-champ name="cout_cumule_par_tete" label="Coût cumulé par tête" type="number" step="0.01" min="0" suffixe="FCFA" required/>
                <x-champ name="marge_pct" label="Marge cible" type="number" step="0.01" min="0" value="0" suffixe="%" required/>
                <label class="flex items-center gap-3 text-sm"><input type="hidden" name="actif" value="0"><input type="checkbox" name="actif" value="1" checked class="size-5"> Active</label>
                <x-champ name="commentaire" label="Commentaire" maxlength="255"/>
                <button class="btn-primaire w-full">Enregistrer</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

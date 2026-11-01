@php use App\Support\Format; $nom = fn ($id) => $aliments[$id]->nom_aliment ?? '#'.$id; @endphp
<x-layouts.app :titre="$plan->nom_plan" :sous-titre="'Plan '.$plan->espece">
    @include('volailles.alimentation._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="carte overflow-x-auto">
            <table class="tableau">
                <thead class="bg-stone-50"><tr><th class="text-right">Semaine</th><th>Aliment</th><th class="text-right">Ration</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($lignes as $l)
                        <tr>
                            <td class="text-right tabular-nums">{{ $l->semaine }}</td>
                            <td>
                                @if ($l->is_transition)
                                    <x-statut ton="info">Transition</x-statut>
                                    {{ $nom($l->id_aliment_source) }} {{ Format::pourcent($l->pct_source) }} → {{ $nom($l->id_aliment_cible) }} {{ Format::pourcent($l->pct_cible) }}
                                @else
                                    {{ $nom($l->id_aliment) }}
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ Format::nombre($l->qte_par_tete, 1) }} g/sujet/jour</td>
                            <td class="text-right">
                                @can('ALI.gerer')
                                    <form method="POST" action="{{ route('alimentation.ligne.supprimer', [$plan->id, $l->id]) }}">@csrf @method('DELETE')<button class="text-sm font-medium text-red-700 hover:underline">Retirer</button></form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-stone-500">Aucune ration.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('ALI.gerer')
            <form method="POST" action="{{ route('alimentation.ligne', $plan->id) }}" class="carte h-fit space-y-3 p-4" id="form-ligne">
                @csrf
                <h2 class="font-semibold text-stone-900">Ajouter une ration</h2>
                <div class="grid grid-cols-2 gap-3">
                    <x-champ name="semaine" label="Semaine" type="number" min="1" required/>
                    <x-champ name="qte_par_tete" label="Ration" type="number" step="0.1" min="0.1" suffixe="g" required/>
                </div>
                <label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_transition" value="0"><input type="checkbox" name="is_transition" value="1" @checked(old('is_transition')) class="size-5" onchange="document.getElementById('bloc-transition').hidden=!this.checked;document.getElementById('bloc-simple').hidden=this.checked"> Transition entre deux aliments</label>
                <div id="bloc-simple" @if(old('is_transition')) hidden @endif>
                    <x-choix name="id_aliment" label="Aliment" :options="$aliments->pluck('nom_aliment', 'id')->all()" vide="Choisir…"/>
                </div>
                <div id="bloc-transition" class="space-y-3" @unless(old('is_transition')) hidden @endunless>
                    <div class="grid grid-cols-[1fr_5rem] gap-2">
                        <x-choix name="id_aliment_source" label="Aliment source" :options="$aliments->pluck('nom_aliment', 'id')->all()" vide="Choisir…"/>
                        <x-champ name="pct_source" label="%" type="number" min="0" max="100" value="60"/>
                    </div>
                    <div class="grid grid-cols-[1fr_5rem] gap-2">
                        <x-choix name="id_aliment_cible" label="Aliment cible" :options="$aliments->pluck('nom_aliment', 'id')->all()" vide="Choisir…"/>
                        <x-champ name="pct_cible" label="%" type="number" min="0" max="100" value="40"/>
                    </div>
                </div>
                <p class="text-xs text-stone-500">Plusieurs rations d’un même aliment dans la même semaine s’additionnent.</p>
                <button class="btn-primaire w-full">Ajouter</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

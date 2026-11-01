@php use App\Support\Format; $types = ['incubateur' => 'Incubateur', 'eclosoir' => 'Éclosoir']; @endphp
<x-layouts.app titre="Équipements" sous-titre="Incubateurs et éclosoirs">
    @include('volailles.incubation._onglets')

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="carte overflow-x-auto">
            <table class="tableau">
                <thead class="bg-stone-50"><tr><th>Nom</th><th>Type</th><th class="text-right">Capacité</th><th class="text-right">Occupé</th><th>Mise en service</th><th></th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($equipements as $e)
                        @if ($edition === $e->id)
                            <tr class="bg-ferme-50/40"><td colspan="6">
                                <form method="POST" action="{{ route('equipements.update', $e->id) }}" class="grid gap-2 sm:grid-cols-5">
                                    @csrf @method('PUT')
                                    <input name="nom" value="{{ old('nom', $e->nom) }}" maxlength="50" required class="champ sm:col-span-2" aria-label="Nom">
                                    <select name="type" class="champ" aria-label="Type">@foreach ($types as $k => $v)<option value="{{ $k }}" @selected($e->type === $k)>{{ $v }}</option>@endforeach</select>
                                    <input name="capacite" type="number" min="1" value="{{ old('capacite', $e->capacite) }}" required class="champ" aria-label="Capacité">
                                    <button class="btn-primaire">Enregistrer</button>
                                    <input type="hidden" name="date_mise_service" value="{{ $e->date_mise_service }}">
                                </form>
                                <p class="mt-1 text-xs text-stone-500">Un équipement dont le nom figure dans des lots d’incubation ne peut pas être renommé (l’historique y fait référence).</p>
                            </td></tr>
                        @else
                            <tr>
                                <td class="font-medium">{{ $e->nom }}</td>
                                <td>{{ $types[$e->type] ?? $e->type }}</td>
                                <td class="text-right tabular-nums">{{ Format::nombre($e->capacite) }}</td>
                                <td class="text-right tabular-nums">{{ Format::nombre($e->occupe) }}</td>
                                <td>{{ Format::date($e->date_mise_service) }}</td>
                                <td class="text-right">@can('INC.gerer')<a href="{{ route('equipements.index', ['modifier' => $e->id]) }}" class="font-medium text-ferme-700 hover:underline">Modifier</a>@endcan</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-stone-500">Aucun équipement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('INC.gerer')
            <form method="POST" action="{{ route('equipements.store') }}" class="carte space-y-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Ajouter un équipement</h2>
                <x-champ name="nom" label="Nom (50 caractères max.)" maxlength="50" required/>
                <x-choix name="type" label="Type" :options="$types"/>
                <x-champ name="capacite" label="Capacité (œufs)" type="number" min="1" required inputmode="numeric"/>
                <x-champ name="date_mise_service" label="Mise en service" type="date" :max="today()->toDateString()"/>
                <button class="btn-primaire w-full">Ajouter</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

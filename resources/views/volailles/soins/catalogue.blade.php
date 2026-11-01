<x-layouts.app titre="Catalogue des soins" sous-titre="Soins et vaccins de référence">
    <x-onglets :liens="['Planning' => route('soins.index'), 'Catalogue' => route('soins.catalogue'), 'Programme poussins' => route('soins.programme')]"/>
    @php $champs = fn ($c = null) => [
        ['nom', 'Nom', $c->nom ?? null, true], ['produit_utilise', 'Produit', $c->produit_utilise ?? null, false], ['dose', 'Dose', $c->dose ?? null, false],
        ['voie_administration', 'Voie', $c->voie_administration ?? null, false], ['espece_cible', 'Espèce cible', $c->espece_cible ?? null, false],
    ]; @endphp
    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-2">
            @forelse ($catalogue as $c)
                @if ($edition === $c->id)
                    <form method="POST" action="{{ route('soins.catalogue.update', $c->id) }}" class="carte grid gap-3 p-4 sm:grid-cols-3">
                        @csrf @method('PUT')
                        @foreach ($champs($c) as [$n, $l, $v, $r])<x-champ :name="$n" :label="$l" :value="$v" :required="$r" :id="$n.'-e'"/>@endforeach
                        <x-choix name="type" label="Type" :options="['soin' => 'Soin', 'vaccin' => 'Vaccin']" :value="$c->type" id="type-e"/>
                        <x-champ name="observation" label="Observation" :value="$c->observation" class="sm:col-span-3" id="obs-e"/>
                        <div class="flex gap-2 sm:col-span-3"><button class="btn-primaire">Enregistrer</button><a href="{{ route('soins.catalogue') }}" class="btn-secondaire">Annuler</a></div>
                    </form>
                @else
                    <div class="carte flex flex-wrap items-center gap-3 p-4 text-sm">
                        <x-statut :ton="$c->type === 'vaccin' ? 'info' : 'neutre'">{{ ucfirst($c->type) }}</x-statut>
                        <div class="min-w-0 flex-1"><div class="font-medium text-stone-900">{{ $c->nom }}</div><div class="text-xs text-stone-500">{{ collect([$c->produit_utilise, $c->dose, $c->voie_administration, $c->espece_cible])->filter()->implode(' · ') ?: '—' }}</div></div>
                        @can('SAV.gerer')<a href="{{ route('soins.catalogue', ['modifier' => $c->id]) }}" class="font-medium text-ferme-700 hover:underline">Modifier</a>@endcan
                    </div>
                @endif
            @empty
                <x-vide icone="heart">Catalogue vide.</x-vide>
            @endforelse
        </div>
        @can('SAV.gerer')
            <form method="POST" action="{{ route('soins.catalogue.store') }}" class="carte h-fit space-y-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Ajouter au catalogue</h2>
                <x-choix name="type" label="Type" :options="['soin' => 'Soin', 'vaccin' => 'Vaccin']"/>
                @foreach ($champs() as [$n, $l, $v, $r])<x-champ :name="$n" :label="$l" :required="$r"/>@endforeach
                <x-champ name="observation" label="Observation" type="textarea"/>
                <button class="btn-primaire w-full">Ajouter</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

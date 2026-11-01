<x-layouts.app titre="Reproducteurs" sous-titre="Bandes parentales de poulets et de cailles">
    <x-slot:actions>
        @can('REP.gerer')<a href="{{ route('reproducteurs.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/>Nouvelle bande</a>@endcan
    </x-slot:actions>

    <x-onglets :liens="['Bandes actives' => route('reproducteurs.index'), 'Réformées' => route('reproducteurs.index', ['vue' => 'reformees'])]"/>

    @if ($bandes->isEmpty())
        <x-vide icone="users">{{ $vue === 'actives' ? 'Aucune bande active. Créez une bande pour saisir la ponte.' : 'Aucune bande réformée.' }}</x-vide>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($bandes as $b)
                <a href="{{ route('reproducteurs.show', $b->id) }}" class="carte block p-4 transition hover:border-ferme-200 hover:shadow">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-stone-900">{{ $b->nom_bande }}</div>
                            <div class="text-xs text-stone-500">{{ ucfirst($b->espece) }} · depuis le {{ \App\Support\Format::date($b->date_mise_en_place) }}</div>
                        </div>
                        @if ($b->reformee)<x-statut>Réformée</x-statut>@endif
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-center">
                        <div class="rounded-lg bg-stone-50 py-2"><div class="text-lg font-bold tabular-nums">{{ \App\Support\Format::nombre($b->nb_femelles) }}</div><div class="text-xs text-stone-500">femelles</div></div>
                        <div class="rounded-lg bg-stone-50 py-2"><div class="text-lg font-bold tabular-nums">{{ \App\Support\Format::nombre($b->nb_males) }}</div><div class="text-xs text-stone-500">mâles</div></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>

<x-layouts.app titre="Plans d’alimentation" sous-titre="Rations par semaine d’âge, affectées aux lots">
    @include('volailles.alimentation._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="grid h-fit gap-3 sm:grid-cols-2">
            @forelse ($plans as $p)
                <a href="{{ route('alimentation.plan', $p->id) }}" class="carte block p-4 hover:border-ferme-200">
                    <div class="font-medium text-stone-900">{{ $p->nom_plan }}</div>
                    <div class="text-xs text-stone-500">{{ $p->espece }} · {{ $p->lignes }} ration(s) · {{ $p->lots }} lot(s) actif(s)</div>
                </a>
            @empty
                <x-vide icone="wheat">Aucun plan.</x-vide>
            @endforelse
        </div>
        @can('ALI.gerer')
            <form method="POST" action="{{ route('alimentation.plans.store') }}" class="carte h-fit space-y-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Nouveau plan</h2>
                <x-champ name="nom_plan" label="Nom" maxlength="100" required/>
                <x-champ name="espece" label="Espèce" value="poulet" maxlength="100" required/>
                <x-champ name="commentaire" label="Commentaire" type="textarea"/>
                <button class="btn-primaire w-full">Créer</button>
            </form>
        @endcan
    </div>
</x-layouts.app>

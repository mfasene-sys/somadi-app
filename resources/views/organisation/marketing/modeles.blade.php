@php $vars = implode(' ', \App\Domaine\Organisation\Marketing::VARIABLES); @endphp
<x-layouts.app titre="Modèles de message">
    @include('organisation.marketing._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-3">
            @forelse ($modeles as $m)
                <details class="carte {{ $m->is_actif ? '' : 'opacity-60' }}">
                    <summary class="flex cursor-pointer list-none gap-3 p-4 text-sm"><span class="flex-1 font-medium">{{ $m->titre }}</span><span class="line-clamp-1 max-w-md text-stone-500">{{ $m->message }}</span></summary>
                    @can('MKT.gerer')
                        <form method="POST" action="{{ route('marketing.modeles.enregistrer', $m->id) }}" enctype="multipart/form-data" class="space-y-3 border-t border-stone-100 p-4">@csrf
                            @include('organisation.marketing._modele', ['m' => $m])<button class="btn-secondaire w-full">Enregistrer</button></form>
                    @endcan
                </details>
            @empty <x-vide icone="chat">Aucun modèle.</x-vide> @endforelse
        </div>
        @can('MKT.gerer')
            <form method="POST" action="{{ route('marketing.modeles.enregistrer') }}" enctype="multipart/form-data" class="carte h-fit space-y-3 p-4">@csrf
                <h2 class="font-semibold text-stone-900">Nouveau modèle</h2>
                @include('organisation.marketing._modele', ['m' => null])<button class="btn-primaire w-full">Créer</button>
                <p class="text-xs text-stone-500">Variables : {{ $vars }}</p></form>
        @endcan
    </div>
</x-layouts.app>

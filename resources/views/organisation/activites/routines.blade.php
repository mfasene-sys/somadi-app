<x-layouts.app titre="Routines" sous-titre="Travaux récurrents, générés en tâches après aperçu">
    @include('organisation.activites._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="carte divide-y divide-stone-100 text-sm">
            @forelse ($routines as $r)
                <a href="{{ route('activites.routines.show', $r->id) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50 {{ $r->actif ? '' : 'opacity-60' }}">
                    <span class="size-3 rounded-full" style="background: {{ $r->couleur_fond }}"></span>
                    <span class="flex-1"><span class="font-medium">{{ $r->titre }}</span><span class="block text-xs text-stone-500">{{ (int) $r->n }} tâche(s){{ $r->equipe ? ' · équipe '.$r->equipe : '' }}</span></span>
                    <x-statut :ton="$r->actif ? 'ok' : 'neutre'">{{ $r->actif ? 'Active' : 'Inactive' }}</x-statut>
                </a>
            @empty <p class="px-4 py-3 text-stone-500">Aucune routine.</p> @endforelse
        </div>
        @if (\App\Domaine\Organisation\Activites::planificateur())
            <form method="POST" action="{{ route('activites.routines.enregistrer') }}" class="carte h-fit space-y-3 p-4">@csrf
                <h2 class="font-semibold text-stone-900">Nouvelle routine</h2>
                @include('organisation.activites._routine', ['r' => null])
                <button class="btn-primaire w-full">Créer</button>
            </form>
        @endif
    </div>
</x-layouts.app>

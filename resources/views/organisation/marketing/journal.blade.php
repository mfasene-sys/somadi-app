@php use App\Domaine\Organisation\Marketing as M; use App\Support\Format; @endphp
<x-layouts.app titre="Journal des actions" sous-titre="Ouvertures de WhatsApp et déclarations d’envoi">
    @include('organisation.marketing._onglets')
    <div class="carte divide-y divide-stone-100 text-sm">
        @forelse ($logs as $l)
            <div class="flex flex-wrap gap-3 px-4 py-2.5"><span class="w-32 tabular-nums text-stone-500">{{ Format::date(substr($l->created_at, 0, 10)) }} {{ substr($l->created_at, 11, 5) }}</span>
                <span class="flex-1">{{ $l->client_nom }} · {{ $l->client_tel_norm }}<span class="block text-xs text-stone-500">{{ $l->titre }} · {{ $l->user_login }}</span></span>
                <x-statut :ton="M::ACTIONS[$l->action][1]">{{ M::ACTIONS[$l->action][0] }}</x-statut></div>
        @empty <p class="px-4 py-3 text-stone-500">Aucune action.</p> @endforelse
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</x-layouts.app>

@php use App\Support\Format; use App\Support\Montant; use App\Domaine\Reservations; @endphp
<x-layouts.app titre="Réservations de poussins" sous-titre="Pré-réservations sur les lots en incubation, confirmées à l’éclosion">
    <x-slot:actions>
        @can('RES.gerer')<a href="{{ route('reservations.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/> Réserver</a>@endcan
    </x-slot:actions>

    @if ($aTraiter->isNotEmpty())
        <section class="mb-6 space-y-2">
            @foreach ($aTraiter as $l)
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-paille-500/40 bg-paille-50 px-4 py-3 text-sm text-paille-800">
                    <x-icone nom="egg" class="size-5"/>
                    <span class="flex-1"><strong>{{ $l->nom_lot }}</strong> a éclos ({{ $l->nombre_eclos }} poussins) : {{ $l->n }} réservation(s) à confirmer, {{ $l->manque }} poussin(s) attendus.</span>
                    @can('RES.gerer')
                        <form method="POST" action="{{ route('reservations.traiter', $l->id) }}">@csrf<button class="btn-primaire min-h-9">Affecter les poussins</button></form>
                    @endcan
                </div>
            @endforeach
        </section>
    @endif

    <section class="mb-6">
        <h2 class="mb-3 font-semibold text-stone-900">Lots en incubation ouverts à la réservation</h2>
        @if ($previsions->isEmpty())
            <p class="carte px-4 py-3 text-sm text-stone-500">Aucun lot en incubation.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($previsions as $p)
                    <div class="carte p-4">
                        <p class="font-medium text-stone-900">{{ $p->nom_lot }}</p>
                        <p class="text-xs text-stone-500">{{ ucfirst($p->espece) }} · éclosion prévue le {{ Format::date($p->date_eclosion_prevue) }}</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums">{{ Format::nombre($p->nombre_prevu) }} <span class="text-sm font-normal text-stone-500">encore disponibles</span></p>
                        @can('RES.gerer')
                            @if ($p->nombre_prevu > 0)<a href="{{ route('reservations.create', ['lot' => $p->id_lot]) }}" class="mt-2 inline-block text-sm font-medium text-ferme-700 hover:underline">Réserver sur ce lot</a>@endif
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <x-onglets :liens="['En cours' => route('reservations.index', ['statut' => 'actives']), 'Livrées et annulées' => route('reservations.index', ['statut' => 'terminees']), 'Toutes' => route('reservations.index', ['statut' => 'toutes'])]"/>
    @if ($reservations->isEmpty())
        <x-vide icone="calendar">Aucune réservation.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100">
            @foreach ($reservations as $r)
                @php([$lib, $ton] = Reservations::libelle($r->statut))
                <a href="{{ route('reservations.show', $r->id_reservation) }}" class="flex min-h-14 items-center gap-3 px-4 py-2.5 text-sm hover:bg-stone-50">
                    <span class="flex-1"><span class="font-medium text-stone-900">{{ $r->nom_client }}</span> · {{ $r->telephone }}
                        <span class="block text-xs text-stone-500">N° {{ $r->id_reservation }} du {{ Format::date($r->date_reservation) }} · {{ $r->nom_lot ?? 'lot supprimé' }}</span></span>
                    <span class="text-right tabular-nums"><strong>{{ $r->quantite_servie ? $r->quantite_servie.' / ' : '' }}{{ $r->quantite }}</strong>
                        <span class="block text-xs text-stone-500">{{ Montant::fcfa((int) $r->quantite * (int) $r->prix_unitaire * 100) }}</span></span>
                    <x-statut :ton="$ton">{{ $lib }}</x-statut>
                </a>
            @endforeach
        </div>
        <div class="mt-3">{{ $reservations->links() }}</div>
    @endif
</x-layouts.app>

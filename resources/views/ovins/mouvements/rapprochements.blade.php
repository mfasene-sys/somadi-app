@php use App\Support\Format; use App\Support\Montant; @endphp
<x-layouts.app titre="Ventes ovines à rapprocher" sous-titre="Sorties de vente enregistrées sans lien avec le registre des ventes (administrateur)">
    @if ($sorties->isEmpty())
        <x-vide icone="check">Toutes les sorties de vente sont liées à une vente.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100 text-sm">
            @foreach ($sorties as $s)
                <a href="{{ route('ovins.rapprochements.show', $s->id_sortie) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50">
                    <span class="w-20 tabular-nums text-stone-500">{{ Format::date($s->date_sortie) }}</span>
                    <span class="flex-1"><strong>{{ $s->code_animal ?: '#'.$s->id_animal }}</strong> · {{ $s->client_nom ?: 'client non indiqué' }}<span class="block text-xs text-stone-500">sortie n° {{ $s->id_sortie }} · {{ $s->utilisateur }}</span></span>
                    <strong class="tabular-nums">{{ $s->prix_unitaire ? Montant::fcfa(Montant::base($s->prix_unitaire)) : '—' }}</strong>
                    <x-icone nom="chevron-right" class="size-4 text-stone-400"/>
                </a>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-stone-500">Ces ventes non liées bloquent la clôture de l’exercice concerné.</p>
    @endif
</x-layouts.app>

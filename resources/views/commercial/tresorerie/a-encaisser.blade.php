@php use App\Support\Montant; use App\Support\Format; use App\Domaine\Ventes; @endphp
<x-layouts.app titre="Reste à encaisser" sous-titre="Ventes non soldées, de la plus ancienne à la plus récente">
    @include('commercial.tresorerie._onglets')
    <div class="mb-4 grid grid-cols-2 gap-3 sm:w-1/2">
        <x-indicateur label="Total dû" :valeur="Montant::fcfa(Montant::base($total))" :ton="Montant::base($total) > 0 ? 'alerte' : null"/>
        <x-indicateur label="Ventes" :valeur="Format::nombre($ventes->total())"/>
    </div>
    @if ($ventes->isEmpty())
        <x-vide icone="check">Toutes les ventes sont encaissées.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100">
            @foreach ($ventes as $v)
                @php($jours = (int) \Illuminate\Support\Carbon::parse($v->date_vente)->diffInDays(today()))
                <a href="{{ route('ventes.show', $v->id) }}" class="flex min-h-14 items-center gap-3 px-4 py-2.5 text-sm hover:bg-stone-50">
                    <span class="flex-1"><span class="font-medium text-stone-900">{{ $v->client_nom }}</span> · {{ $v->client_tel }}
                        <span class="block text-xs text-stone-500">N° {{ $v->id }} du {{ Format::date($v->date_vente) }} · {{ Ventes::libelle($v->espece) }} · il y a {{ $jours }} j</span></span>
                    <span class="text-right"><strong class="tabular-nums">{{ Montant::fcfa(Montant::base($v->montant_reste_encaisser)) }}</strong>
                        <span class="block text-xs text-stone-500">sur {{ Montant::fcfa(Montant::base($v->montant_total)) }}</span></span>
                    <x-icone nom="chevron-right" class="size-4 text-stone-400"/>
                </a>
            @endforeach
        </div>
        <div class="mt-3">{{ $ventes->links() }}</div>
    @endif
</x-layouts.app>

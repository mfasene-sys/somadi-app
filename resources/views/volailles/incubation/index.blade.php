@php use App\Support\Format; use App\Domaine\Incubation; @endphp
<x-layouts.app titre="Incubation" sous-titre="Chargement, mirage, transfert et éclosion">
    @include('volailles.incubation._onglets')

    @if ($vue === 'en_cours')
        <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Occupation des équipements</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($equipements as $e)
                    @php $pct = $e->capacite ? min(100, round(100 * $e->occupe / $e->capacite)) : 0; @endphp
                    <div class="carte p-3">
                        <div class="flex items-center justify-between text-sm"><span class="font-medium text-stone-900">{{ $e->nom }}</span><span class="text-xs text-stone-500">{{ $e->type === 'eclosoir' ? 'Éclosoir' : 'Incubateur' }}</span></div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full {{ $pct >= 90 ? 'bg-paille-500' : 'bg-ferme-600' }}" style="width: {{ $pct }}%"></div></div>
                        <div class="mt-1 text-xs tabular-nums text-stone-500">{{ Format::nombre($e->occupe) }} / {{ Format::nombre($e->capacite) }} · {{ Format::nombre($e->libre) }} places libres</div>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Aucun équipement : déclarez vos incubateurs et éclosoirs dans l’onglet Équipements.</p>
                @endforelse
            </div>
        </section>
    @endif

    @if ($lots->isEmpty())
        <x-vide icone="sun">{{ $vue === 'en_cours' ? 'Aucune incubation en cours.' : 'Aucune incubation terminée.' }}</x-vide>
    @else
        <div class="space-y-2">
            @foreach ($lots as $l)
                @php $stade = Incubation::stade($l); @endphp
                <a href="{{ route('incubation.show', $l->id) }}" class="carte flex flex-wrap items-center gap-x-4 gap-y-1 p-4 transition hover:border-ferme-200">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-stone-900">{{ $l->nom_lot ?: 'Lot n° '.$l->id }}</div>
                        <div class="text-xs text-stone-500">{{ ucfirst($l->espece) }} · {{ $l->nom_bande ?? 'œufs achetés' }} · {{ $l->incubateur }} · chargé le {{ Format::date($l->date_entree) }}</div>
                    </div>
                    <div class="text-right text-sm">
                        @if ($vue === 'en_cours')
                            <div class="tabular-nums"><strong>{{ Format::nombre(Incubation::disponibles($l)) }}</strong> œufs</div>
                            <div class="text-xs text-stone-500">éclosion prévue {{ Format::date($l->date_eclosion_previsionnelle) }}</div>
                        @else
                            <div class="tabular-nums"><strong>{{ Format::nombre($l->nombre_eclos) }}</strong> éclos · {{ Format::pourcent($l->taux_eclosion) }}</div>
                            <div class="text-xs text-stone-500">le {{ Format::date($l->date_eclosion) }}</div>
                        @endif
                    </div>
                    <x-statut :ton="$stade['ton']">{{ $stade['libelle'] }}</x-statut>
                </a>
            @endforeach
        </div>
        <div class="mt-3">{{ $lots->links() }}</div>
    @endif
</x-layouts.app>

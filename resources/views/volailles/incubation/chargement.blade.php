@php use App\Support\Format; @endphp
<x-layouts.app titre="Charger un incubateur" sous-titre="Sélectionnez des lots « À incuber » d’une même bande">
    @include('volailles.incubation._onglets')

    @if ($parBande->isEmpty())
        <x-vide icone="egg">Aucun lot « À incuber » disponible. Affectez d’abord des œufs depuis le stock.</x-vide>
    @elseif ($incubateurs->isEmpty())
        <x-vide icone="sun">Aucun incubateur déclaré. Ajoutez-en un dans l’onglet Équipements.</x-vide>
    @else
        @foreach ($parBande as $idBande => $g)
            <form method="POST" action="{{ route('incubation.store') }}" class="carte mb-5 p-4 sm:p-5">
                @csrf
                <x-cle/>
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-semibold text-stone-900">{{ $g['nom'] }} <span class="font-normal text-stone-500">· {{ ucfirst($g['espece']) }}</span></h2>
                    <span class="text-sm text-stone-500">{{ Format::nombre($g['total']) }} œufs disponibles</span>
                </div>
                <div class="mb-4 divide-y divide-stone-100 rounded-lg border border-stone-200">
                    @foreach ($g['lots'] as $l)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-stone-50">
                            <input type="checkbox" name="lots[]" value="{{ $l->id }}" checked class="size-5">
                            <span class="flex-1">Lot n° {{ $l->id }} · ramassage du {{ Format::date($l->date_ramassage) }}</span>
                            <span class="font-semibold tabular-nums">{{ $l->quantite }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-choix name="id_incubateur" label="Incubateur" :options="$incubateurs->mapWithKeys(fn ($e) => [$e->id => $e->nom.' — '.Format::nombre($e->libre).' places libres'])->all()" :id="'inc-'.$idBande"/>
                    <x-champ name="date_entree" label="Date de chargement" type="date" :value="$g['proposee'] && $g['proposee'] >= today()->toDateString() ? $g['proposee'] : today()->toDateString()" required :id="'date-'.$idBande"
                             :aide="$g['proposee'] ? 'Proposé : jeudi '.Format::date($g['proposee']).' (collectes d’une même semaine jeudi–mercredi). Modifiable.' : null"/>
                    <x-champ name="observations" label="Observations" :id="'obs-'.$idBande"/>
                </div>
                <p class="mt-3 text-xs text-stone-500">Éclosion prévue {{ $g['espece'] === 'caille' ? '17' : '21' }} jours après le chargement.</p>
                <div class="mt-4 flex justify-end"><button type="submit" class="btn-primaire">Charger</button></div>
            </form>
        @endforeach
    @endif
</x-layouts.app>
